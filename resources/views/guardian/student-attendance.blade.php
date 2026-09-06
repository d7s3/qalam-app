<x-layouts.role-shell>
    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    @php
        $guardian = auth()->guard('guardian')->user();
        $student = $guardian->students()->findOrFail($studentId);

        // The month on view is a Hijri one — the academy's own — navigated by
        // ?month=1448-03. A Gregorian month would begin and end on days nobody
        // here recognises.
        $monthParam = request('month');
        [$paramYear, $paramMonth] = $monthParam && str_contains($monthParam, '-')
            ? array_map('intval', explode('-', $monthParam, 2))
            : [null, null];

        $grid = \App\Support\HijriDate::monthGrid($paramYear, $paramMonth);

        $prev = \App\Support\HijriDate::shiftMonth($grid['year'], $grid['month'], -1);
        $next = \App\Support\HijriDate::shiftMonth($grid['year'], $grid['month'], 1);
        $prevMonth = sprintf('%04d-%02d', $prev['year'], $prev['month']);
        $nextMonth = sprintf('%04d-%02d', $next['year'], $next['month']);
        $canGoNext = $grid['last'] < now()->toDateString();

        // Read over the month's own span, since it crosses two Gregorian ones.
        $attendances = $student->attendances()
            ->whereBetween('date', [$grid['first'], $grid['last']])
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        $startOffset = $grid['leadingBlanks'];

        $statusConfig = [
            'present' => ['label' => 'حاضر', 'bg' => 'bg-emerald-100 dark:bg-emerald-500/20', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500'],
            'late' => ['label' => 'متأخر', 'bg' => 'bg-amber-100  dark:bg-amber-500/20', 'text' => 'text-amber-700  dark:text-amber-400', 'dot' => 'bg-amber-500'],
            'absent' => ['label' => 'غائب', 'bg' => 'bg-red-100    dark:bg-red-500/20', 'text' => 'text-red-700    dark:text-red-400', 'dot' => 'bg-red-500'],
            'excused' => ['label' => 'غياب بعذر', 'bg' => 'bg-blue-100  dark:bg-blue-500/20', 'text' => 'text-blue-700   dark:text-blue-400', 'dot' => 'bg-blue-500'],
        ];

        // Summary counts
        $summary = [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'excused' => 0,
        ];
        foreach ($attendances as $att) {
            if (isset($summary[$att->status])) {
                $summary[$att->status]++;
            }
        }
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-6">

        {{-- Back + Title --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('guardian.student', $student->id) }}"
                class="p-2 rounded-lg text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800   s">
                <flux:icon icon="arrow-right" class="size-5" />
            </a>
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">سجل الحضور</h1>
                <p class="text-sm text-zinc-500">{{ $student->name }}</p>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($statusConfig as $key => $cfg)
                <div
                    class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 flex items-center gap-3">
                    <div class="size-3 rounded-full {{ $cfg['dot'] }} shrink-0"></div>
                    <div>
                        <p class="text-xs text-zinc-500">{{ $cfg['label'] }}</p>
                        <p class="text-xl font-bold text-zinc-900 dark:text-white">{{ $summary[$key] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Calendar --}}
        <div class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">

            {{-- Month navigation --}}
            <div class="flex items-center justify-between mb-6">
                <a href="{{ route('guardian.student.attendance', ['id' => $student->id, 'month' => $prevMonth]) }}"
                    class="p-2 rounded-lg text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800   s">
                    <flux:icon icon="chevron-right" class="size-5" />
                </a>

                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                    <span class="flex flex-col items-center leading-tight">
                        <span>{{ $grid['label'] }}</span>
                        <span dir="ltr" class="text-[11px] font-medium tabular-nums text-zinc-400">{{ $grid['span'] }}</span>
                    </span>
                </h2>

                @if($canGoNext)
                    <a href="{{ route('guardian.student.attendance', ['id' => $student->id, 'month' => $nextMonth]) }}"
                        class="p-2 rounded-lg text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800   s">
                        <flux:icon icon="chevron-left" class="size-5" />
                    </a>
                @else
                    <div class="size-9"></div>
                @endif
            </div>

            {{-- Day headers (Sat → Fri) --}}
            <div class="grid grid-cols-7 mb-2">
                @foreach(['س', 'أ', 'إ', 'ث', 'خ', 'ج', 'ع'] as $dayLabel)
                    <div class="text-center text-xs font-medium text-zinc-400 py-2">{{ $dayLabel }}</div>
                @endforeach
            </div>

            {{-- Calendar cells --}}
            <div class="grid grid-cols-7 gap-1">
                {{-- Empty offset cells --}}
                @for($i = 0; $i < $startOffset; $i++)
                    <div></div>
                @endfor

                {{-- Day cells --}}
                @foreach($grid['days'] as $cell)
                    @php
                        $day = $cell['hijri'];
                        $dateStr = $cell['date'];
                        $attendance = $attendances[$dateStr] ?? null;
                        $isToday = $dateStr === today()->format('Y-m-d');
                        $cfg = $attendance ? $statusConfig[$attendance->status] ?? null : null;
                    @endphp
                    <div
                        class="relative flex flex-col items-center justify-center rounded-lg py-2 min-h-[52px]
                                    {{ $cfg ? $cfg['bg'] : '' }}
                                    {{ $isToday && !$cfg ? 'ring-2 ring-blue-400 ring-offset-1 dark:ring-offset-zinc-800' : '' }}">
                        <span
                            class="text-sm font-medium {{ $cfg ? $cfg['text'] : 'text-zinc-600 dark:text-zinc-400' }}">
                            {{ $day }}
                        </span>
                        <span dir="ltr" class="text-[9px] font-medium tabular-nums text-zinc-400 dark:text-zinc-500">
                            {{ substr($dateStr, 5) }}
                        </span>
                        @if($cfg)
                            <span class="text-[10px] {{ $cfg['text'] }} opacity-75">{{ $cfg['label'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap gap-4 mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                @foreach($statusConfig as $key => $cfg)
                    <div class="flex items-center gap-1.5">
                        <div class="size-2.5 rounded-full {{ $cfg['dot'] }}"></div>
                        <span class="text-xs text-zinc-500">{{ $cfg['label'] }}</span>
                    </div>
                @endforeach
                <div class="flex items-center gap-1.5">
                    <div class="size-2.5 rounded-full ring-2 ring-blue-400 bg-transparent"></div>
                    <span class="text-xs text-zinc-500">اليوم</span>
                </div>
            </div>
        </div>
    </div>
</x-layouts.role-shell>