<div class="space-y-8 p-4 sm:p-6" dir="rtl">

    {{-- The head every home page opens on in the October design. --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-[28px] font-bold text-zinc-900 dark:text-white">{{ __('الرئيسية') }}</h1>
            <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $home['greeting'] }} {{ $home['firstName'] }}، {{ __('هذه الأكاديمية هذا الأسبوع.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="flex h-10 items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                <flux:icon icon="calendar" class="size-4" />{{ $home['weekLabel'] }}
            </span>
            <flux:button :href="route('manager.reports')" wire:navigate icon="arrow-down-tray">{{ __('التقارير') }}</flux:button>
        </div>
    </div>

    {{-- Four figures, each against last week where there is one. --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-5 xl:grid-cols-4">
        <x-home.stat icon="users" :label="__('الطلاب المشاركون')" :value="number_format($home['activeStudents'])"
            :change="$home['joinedThisMonth'] > 0 ? ['up' => true, 'text' => '+'.$home['joinedThisMonth']] : null"
            :caption="$home['cohortsLine'].($home['joinedThisMonth'] > 0 ? '، '.__('ومن انضم هذا الشهر') : '')" />
        <x-home.stat icon="calendar-days" :label="__('نسبة الحضور')" :value="$home['attendance'] !== null ? $home['attendance'].'%' : '—'"
            :change="$home['attendanceChange']" :caption="$home['attendance'] !== null ? __('مقارنة بالأسبوع الماضي') : __('لم يُسجَّل حضور هذا الأسبوع')" />
        <x-home.stat icon="book-open" :label="__('صفحات الحفظ')" :value="number_format($home['weekPages'])"
            :change="$home['pagesChange']" :caption="__('هذا الأسبوع، مقارنة بالماضي')" />
        <x-home.stat icon="user-plus" :label="__('طلبات التسجيل')" :value="$home['pendingRequests']"
            :caption="$home['pendingRequests'] > 0 ? __('راجعها الآن') : __('لا طلبات تنتظرك')"
            :href="$home['pendingRequests'] > 0 ? route('manager.pending-approvals') : null"
            :class="$home['pendingRequests'] > 0 ? 'ring-1 ring-maroon/30 dark:ring-red-secondary/30' : ''" />
    </div>

    {{-- Memorisation: the last three months, then each day of this week. --}}
    <div class="flex flex-wrap gap-5">
        <x-home.panel icon="chart-bar" :title="__('تقدّم الحفظ')" class="flex-[999_1_520px]">
            <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="text-[34px] font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($home['monthPages']) }}</span>
                <span class="text-zinc-500">{{ __('صفحة هذا الشهر') }}</span>
            </div>
            <div class="mt-4">
                <x-home.month-chart :months="$home['months']" />
            </div>
        </x-home.panel>

        <x-home.panel icon="book-open" :title="__('الحفظ اليومي')" class="flex flex-[1_1_320px] flex-col">
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-[34px] font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($home['weekDayPages']) }}</span>
                <span class="text-zinc-500">{{ __('صفحة هذا الأسبوع') }}</span>
            </div>
            <x-home.week-bars :days="$home['days']" />
        </x-home.panel>
    </div>

    {{-- Every cohort's week side by side, and how the students stand. --}}
    <div class="flex flex-wrap gap-5">
        <x-home.panel icon="circle-stack" :title="__('أداء الدفعات')" :count="count($home['cohorts'])" class="flex-[999_1_560px]"
            x-data="{
                sort: 'pages',
                dir: -1,
                programme: '',
                rows: {{ Js::from(collect($home['cohorts'])->map(fn ($cohort) => [
                    'id' => $cohort['id'],
                    'name' => $cohort['name'],
                    'students' => $cohort['students'],
                    'attendance' => $cohort['attendance'] ?? -1,
                    'pages' => $cohort['pages'],
                ])->values()) }},
                by(key) {
                    this.dir = this.sort === key ? -this.dir : (key === 'name' ? 1 : -1);
                    this.sort = key;
                },
                order(id) {
                    const byName = (a, b) => a.name.localeCompare(b.name, 'ar');
                    const sorted = [...this.rows].sort((a, b) => this.sort === 'name'
                        ? byName(a, b) * this.dir
                        : (a[this.sort] - b[this.sort]) * this.dir || byName(a, b));

                    return sorted.findIndex(row => row.id === id);
                },
            }">
            @if (count($home['programmes']) > 1)
                <x-slot:actions>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" x-on:click="programme = ''"
                            class="h-8 rounded-lg border px-3 text-xs font-semibold transition"
                            x-bind:class="programme === '' ? 'border-maroon bg-maroon text-white' : 'border-zinc-200 text-zinc-600 hover:border-maroon/40 dark:border-zinc-700 dark:text-zinc-300'">{{ __('الكل') }}</button>
                        @foreach ($home['programmes'] as $programme)
                            <button type="button" x-on:click="programme = @js($programme)"
                                class="h-8 rounded-lg border px-3 text-xs font-semibold transition"
                                x-bind:class="programme === @js($programme) ? 'border-maroon bg-maroon text-white' : 'border-zinc-200 text-zinc-600 hover:border-maroon/40 dark:border-zinc-700 dark:text-zinc-300'">{{ $programme }}</button>
                        @endforeach
                    </div>
                </x-slot:actions>
            @endif

            @if ($home['cohorts'] === [])
                <p class="py-12 text-center text-zinc-500">{{ __('لا دفعات بعد.') }}</p>
            @else
                <div class="mt-4 hidden grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)_4.5rem_minmax(0,1.5fr)_7rem] gap-x-4 border-b border-zinc-200 pb-2 text-[13px] font-medium text-zinc-500 dark:border-zinc-800 md:grid">
                    @foreach (['name' => __('الدفعة'), 'teachers' => __('المعلم'), 'students' => __('الطلاب'), 'attendance' => __('الحضور'), 'pages' => __('الحفظ')] as $key => $label)
                        @if ($key === 'teachers')
                            <span>{{ $label }}</span>
                        @else
                            <button type="button" x-on:click="by('{{ $key }}')" class="flex items-center gap-1 text-start transition hover:text-zinc-900 dark:hover:text-white"
                                x-bind:class="sort === '{{ $key }}' && 'text-zinc-900 dark:text-white'">
                                {{ $label }}
                                <flux:icon icon="chevron-up-down" variant="micro" class="size-3.5" x-show="sort !== '{{ $key }}'" />
                                <flux:icon icon="chevron-down" variant="micro" class="size-3.5" x-cloak x-show="sort === '{{ $key }}' && dir === -1" />
                                <flux:icon icon="chevron-up" variant="micro" class="size-3.5" x-cloak x-show="sort === '{{ $key }}' && dir === 1" />
                            </button>
                        @endif
                    @endforeach
                </div>

                <div class="flex flex-col">
                    @foreach ($home['cohorts'] as $cohort)
                        @php
                            $rate = $cohort['attendance'];
                            $pagesChange = \App\Services\HomeFiguresService::change($cohort['pages'], $cohort['pagesBefore'], false);
                        @endphp
                        <div wire:key="cohort-{{ $cohort['id'] }}" data-cohort="{{ $cohort['id'] }}"
                            x-show="programme === '' || programme === @js($cohort['programme'])"
                            x-bind:style="{ order: order({{ $cohort['id'] }}) }"
                            class="grid grid-cols-3 items-center gap-x-4 gap-y-2 border-b border-zinc-100 py-3.5 last:border-b-0 dark:border-zinc-800 md:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)_4.5rem_minmax(0,1.5fr)_7rem]">
                            <div class="col-span-3 flex min-w-0 items-center gap-3 md:col-span-1">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-zinc-200 font-semibold text-maroon dark:border-zinc-700 dark:text-red-secondary">{{ mb_substr(preg_replace('/^ال/u', '', $cohort['name']), 0, 1) }}</span>
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-zinc-900 dark:text-white">{{ $cohort['name'] }}</div>
                                    <div class="text-xs text-zinc-500">{{ $cohort['programme'] }}</div>
                                </div>
                            </div>
                            <div class="col-span-3 min-w-0 truncate text-sm md:col-span-1">
                                @if ($cohort['teachers'] !== '')
                                    <span class="text-zinc-600 dark:text-zinc-300">{{ $cohort['teachers'] }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
                                        <flux:icon icon="exclamation-triangle" variant="micro" class="size-3.5" />{{ __('بلا معلم') }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-sm">
                                <span class="text-zinc-500 md:hidden">{{ __('الطلاب') }}</span>
                                <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $cohort['students'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                @if ($rate !== null)
                                    <div class="h-1.5 min-w-10 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div @class(['h-full rounded-full', 'bg-emerald-500' => $rate >= 80, 'bg-amber-500' => $rate >= 60 && $rate < 80, 'bg-red-500' => $rate < 60]) style="width: {{ min(100, $rate) }}%"></div>
                                    </div>
                                    <span class="w-10 font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $rate }}%</span>
                                @else
                                    <span class="text-zinc-400">{{ __('لا حضور') }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5 text-sm">
                                <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $cohort['pages'] }}</span>
                                <span class="text-zinc-500">{{ __('ص') }}</span>
                                @if ($cohort['pages'] + $cohort['pagesBefore'] > 0)
                                    <x-home.change :change="$pagesChange" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-home.panel>

        <x-home.panel icon="chart-pie" :title="__('حالات الطلاب')" class="flex-[1_1_320px]">
            <x-home.status-ring :standing="$home['standing']" :empty="__('لا طلاب في الأكاديمية بعد.')" />
        </x-home.panel>
    </div>

    {{-- What the academy did, before what it is: the six figures and the
         relations between them, counted inside the reader's own reach. --}}
    <livewire:shared.activity-pulse />

    {{-- ══════════════════════════════════════════ --}}
    {{--  SECTION 1 – ATTENDANCE                   --}}
    {{-- ══════════════════════════════════════════ --}}
    <div class="space-y-4">

        {{-- Header + period selector --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <x-section-heading icon="user-group" level="lg">
                إحصائيات الحضور

                <x-slot:detail>
                    {{ $attDates['label'] }}
                        @if($attDates['from'] !== $attDates['to'])
                            &nbsp;·&nbsp; <x-hijri-date :date="\Carbon\Carbon::parse($attDates['from'])" /> → <x-hijri-date :date="\Carbon\Carbon::parse($attDates['to'])" />
                        @else
                            &nbsp;·&nbsp; <x-hijri-date :date="\Carbon\Carbon::parse($attDates['from'])" />
                        @endif
                </x-slot:detail>
            </x-section-heading>

            {{-- Period Chips --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach([
                    'today'     => 'اليوم',
                    'yesterday' => 'أمس',
                    'last_data' => 'آخر يوم بيانات',
                    'last_week' => 'الأسبوع الماضي',
                    'custom'    => 'تاريخ مخصص',
                ] as $key => $label)
                    <button wire:click="setAttendancePeriod('{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all
                            {{ $attendancePeriod === $key
                                ? 'bg-maroon border-maroon text-white'
                                : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:border-maroon/40' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Custom date range --}}
        @if($attendancePeriod === 'custom')
            <div class="flex flex-wrap items-center gap-3 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="flex flex-col gap-1 min-w-[160px]">
                    <label class="text-xs font-medium text-zinc-500">من تاريخ</label>
                    <livewire:manager.hijri-datepicker wire:model.live="attFrom" />
                </div>
                <div class="flex flex-col gap-1 min-w-[160px]">
                    <label class="text-xs font-medium text-zinc-500">إلى تاريخ</label>
                    <livewire:manager.hijri-datepicker wire:model.live="attTo" />
                </div>
            </div>
        @endif

        {{-- Content --}}
        @php $att = $attendanceData; @endphp

        @if($att['isSingleDay'] && $att['isSchoolDay'] === false)
            {{-- Not a school day --}}
            <div class="flex items-center gap-4 p-5 rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20">
                <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-800/50 text-amber-600 dark:text-amber-300">
                    <flux:icon icon="no-symbol" class="size-7" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-amber-800 dark:text-amber-300">لا يوجد دوام اليوم</flux:heading>
                    <p class="text-amber-700/80 dark:text-amber-400 text-sm mt-0.5">هذا اليوم ليس ضمن أيام الدوام في التقويم الأكاديمي.</p>
                </div>
            </div>

        @elseif($att['total'] === 0)
            {{-- School day but no data --}}
            <x-empty-note title="لم يُسجَّل شيءٌ بعد">
                لا توجد سجلات حضور لهذا الاختيار. ربما لم يتم تسجيل الحضور بعد.
            </x-empty-note>

        @else
            {{-- Stats Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                {{-- Present --}}
                <div class="relative overflow-hidden rounded-2xl border border-emerald-100 dark:border-emerald-900/50 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                    <div class="absolute top-0 right-0 w-1.5 h-full bg-emerald-500 rounded-r-2xl"></div>
                    <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 mb-1 uppercase tracking-wide">الحضور</p>
                    <p class="text-4xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($att['present']) }}</p>
                    @if($att['total'] > 0)
                        <div class="mt-2 w-full bg-emerald-100 dark:bg-emerald-900/30 rounded-full h-1.5 overflow-hidden">
                            <div class="h-1.5 rounded-full bg-emerald-500 transition-all duration-700"
                                style="width: {{ round($att['present'] / $att['total'] * 100) }}%"></div>
                        </div>
                        <p class="text-xs text-zinc-400 mt-1">{{ round($att['present'] / $att['total'] * 100) }}% من المسجلين</p>
                    @endif
                </div>

                {{-- Absent --}}
                <div class="relative overflow-hidden rounded-2xl border border-rose-100 dark:border-rose-900/50 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                    <div class="absolute top-0 right-0 w-1.5 h-full bg-rose-500 rounded-r-2xl"></div>
                    <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 mb-1 uppercase tracking-wide">الغياب</p>
                    <p class="text-4xl font-black text-rose-600 dark:text-rose-400">{{ number_format($att['absent']) }}</p>
                    @if($att['total'] > 0)
                        <div class="mt-2 w-full bg-rose-100 dark:bg-rose-900/30 rounded-full h-1.5 overflow-hidden">
                            <div class="h-1.5 rounded-full bg-rose-500 transition-all duration-700"
                                style="width: {{ round($att['absent'] / $att['total'] * 100) }}%"></div>
                        </div>
                        <p class="text-xs text-zinc-400 mt-1">{{ round($att['absent'] / $att['total'] * 100) }}% من المسجلين</p>
                    @endif
                </div>

                {{-- Total Recorded --}}
                <div class="relative overflow-hidden rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                    <div class="absolute top-0 right-0 w-1.5 h-full bg-zinc-400 rounded-r-2xl"></div>
                    <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 mb-1 uppercase tracking-wide">إجمالي المسجلين</p>
                    <p class="text-4xl font-black text-zinc-700 dark:text-zinc-200">{{ number_format($att['total']) }}</p>
                    <p class="text-xs text-zinc-400 mt-3">تسجيلات في الفترة المحددة</p>
                </div>

                {{-- Attendance Rate --}}
                <div class="relative overflow-hidden rounded-2xl border border-indigo-100 dark:border-indigo-900/50 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                    <div class="absolute top-0 right-0 w-1.5 h-full bg-indigo-500 rounded-r-2xl"></div>
                    <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 mb-1 uppercase tracking-wide">نسبة الحضور</p>
                    @php $rate = $att['total'] > 0 ? round($att['present'] / $att['total'] * 100) : 0; @endphp
                    <p class="text-4xl font-black {{ $rate >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($rate >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">
                        {{ $rate }}%
                    </p>
                    <p class="text-xs text-zinc-400 mt-3">{{ $rate >= 80 ? 'ممتاز' : ($rate >= 60 ? 'مقبول' : 'ضعيف') }}</p>
                </div>
            </div>

            {{-- Stage breakdown --}}
            @if(count($att['stageRows']) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                    @foreach($att['stageRows'] as $row)
                        @php $stageRate = $row['total'] > 0 ? round($row['present'] / $row['total'] * 100) : 0; @endphp
                        <div class="flex items-center justify-between p-4 rounded-xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/30">
                            <div>
                                <p class="font-semibold text-zinc-800 dark:text-zinc-200 text-sm">{{ $row['name'] }}</p>
                                <p class="text-xs text-zinc-500 mt-0.5">
                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $row['present'] }}</span>
                                    / {{ $row['total'] }} مسجل
                                </p>
                            </div>
                            <div class="text-lg font-black {{ $stageRate >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($stageRate >= 60 ? 'text-amber-600' : 'text-rose-600') }}">
                                {{ $stageRate }}%
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    <div class="h-px bg-zinc-100 dark:bg-zinc-800 rounded-full"></div>

    {{-- ══════════════════════════════════════════ --}}
    {{--  SECTION 2 – QURAN ACTIVITY               --}}
    {{-- ══════════════════════════════════════════ --}}
    <div class="space-y-4">

        {{-- Header + period selector --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <x-section-heading icon="book-open" level="lg">
                إحصائيات الحفظ والمراجعة

                <x-slot:detail>
                    {{ $quranDates['label'] }}
                        @if($quranDates['from'] !== $quranDates['to'])
                            &nbsp;·&nbsp; <x-hijri-date :date="\Carbon\Carbon::parse($quranDates['from'])" /> → <x-hijri-date :date="\Carbon\Carbon::parse($quranDates['to'])" />
                        @else
                            &nbsp;·&nbsp; <x-hijri-date :date="\Carbon\Carbon::parse($quranDates['from'])" />
                        @endif
                </x-slot:detail>
            </x-section-heading>

            {{-- Period Chips --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach([
                    'today'     => 'اليوم',
                    'yesterday' => 'أمس',
                    'last_data' => 'آخر يوم بيانات',
                    'last_week' => 'الأسبوع الماضي',
                    'custom'    => 'تاريخ مخصص',
                ] as $key => $label)
                    <button wire:click="setQuranPeriod('{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all
                            {{ $quranPeriod === $key
                                ? 'bg-maroon border-maroon text-white'
                                : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:border-maroon/40' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Custom date range --}}
        @if($quranPeriod === 'custom')
            <div class="flex flex-wrap items-center gap-3 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="flex flex-col gap-1 min-w-[160px]">
                    <label class="text-xs font-medium text-zinc-500">من تاريخ</label>
                    <livewire:manager.hijri-datepicker wire:model.live="quranFrom" />
                </div>
                <div class="flex flex-col gap-1 min-w-[160px]">
                    <label class="text-xs font-medium text-zinc-500">إلى تاريخ</label>
                    <livewire:manager.hijri-datepicker wire:model.live="quranTo" />
                </div>
            </div>
        @endif

        {{-- Content --}}
        @php $q = $quranData; @endphp

        @if($q['isSingleDay'] && $q['isSchoolDay'] === false)
            <div class="flex items-center gap-4 p-5 rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20">
                <div class="p-3 rounded-xl bg-amber-100 dark:bg-amber-800/50 text-amber-600 dark:text-amber-300">
                    <flux:icon icon="no-symbol" class="size-7" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-amber-800 dark:text-amber-300">لا يوجد دوام اليوم</flux:heading>
                    <p class="text-amber-700/80 dark:text-amber-400 text-sm mt-0.5">هذا اليوم ليس ضمن أيام الدوام في التقويم الأكاديمي.</p>
                </div>
            </div>

        @elseif(! $q['hasData'])
            <x-empty-note title="لم يُسجَّل شيءٌ بعد">
                لا توجد جلسات تسميع مسجلة لهذا الاختيار.
            </x-empty-note>

        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Hifz sessions --}}
                <div class="relative overflow-hidden rounded-2xl border border-indigo-100 dark:border-indigo-900/50 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                    <div class="absolute top-0 right-0 w-1.5 h-full bg-indigo-500 rounded-r-2xl"></div>
                    <div class="flex items-center gap-2 mb-2">
                        <flux:icon icon="book-open" class="size-4 text-indigo-500" />
                        <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">جلسات الحفظ</p>
                    </div>
                    <p class="text-4xl font-black text-indigo-600 dark:text-indigo-400">{{ number_format($q['hifzSessions']) }}</p>
                    <p class="text-xs text-zinc-400 mt-2">جلسة تسميع حفظ مسجلة</p>
                </div>

                {{-- Review sessions --}}
                <div class="relative overflow-hidden rounded-2xl border border-purple-100 dark:border-purple-900/50 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                    <div class="absolute top-0 right-0 w-1.5 h-full bg-purple-500 rounded-r-2xl"></div>
                    <div class="flex items-center gap-2 mb-2">
                        <flux:icon icon="arrow-path" class="size-4 text-purple-500" />
                        <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">جلسات المراجعة</p>
                    </div>
                    <p class="text-4xl font-black text-purple-600 dark:text-purple-400">{{ number_format($q['reviewSessions']) }}</p>
                    <p class="text-xs text-zinc-400 mt-2">جلسة مراجعة مسجلة</p>
                </div>

                {{-- Excellent grades --}}
                <div class="relative overflow-hidden rounded-2xl border border-amber-100 dark:border-amber-900/50 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                    <div class="absolute top-0 right-0 w-1.5 h-full bg-amber-500 rounded-r-2xl"></div>
                    <div class="flex items-center gap-2 mb-2">
                        <flux:icon icon="star" class="size-4 text-amber-500" variant="solid" />
                        <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">تقييمات ممتاز</p>
                    </div>
                    <p class="text-4xl font-black text-amber-600 dark:text-amber-400">{{ number_format($q['excellentCount']) }}</p>
                    <p class="text-xs text-zinc-400 mt-2">جلسة بتقييم ممتاز</p>
                </div>
            </div>

            {{-- Stage breakdown --}}
            @if(count($q['stageRows']) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                    @foreach($q['stageRows'] as $row)
                        <div class="flex items-center justify-between p-4 rounded-xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/30">
                            <div>
                                <p class="font-semibold text-zinc-800 dark:text-zinc-200 text-sm">{{ $row['name'] }}</p>
                                <p class="text-xs text-zinc-500 mt-0.5 flex gap-3">
                                    <span><span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ $row['hifz'] }}</span> حفظ</span>
                                    <span><span class="text-purple-600 dark:text-purple-400 font-bold">{{ $row['review'] }}</span> مراجعة</span>
                                </p>
                            </div>
                            <flux:icon icon="book-open" class="size-5 text-zinc-300 dark:text-zinc-600" />
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

</div>
