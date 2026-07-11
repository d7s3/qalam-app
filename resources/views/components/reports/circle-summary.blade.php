@props(['report', 'showCircleColumn' => false])

@php
    $totals = $report['totals'];
    $perStudent = $report['perStudent'];
@endphp

<div class="space-y-6">

    {{-- Totals cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex items-center gap-2 mb-1">
                <flux:icon icon="book-open" class="size-4 text-emerald-500" />
                <p class="text-xs text-zinc-500">حفظ القرآن</p>
            </div>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($totals['hifz']['ayahs']) }} <span class="text-sm font-medium">آية</span></p>
            <p class="text-xs text-zinc-400 mt-1">
                {{ $totals['hifz']['days'] }} يوم تسميع
                @if($totals['hifz']['average'] !== null)
                    · متوسط التقييم {{ $totals['hifz']['average'] }}/3
                @endif
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex items-center gap-2 mb-1">
                <flux:icon icon="arrow-path" class="size-4 text-blue-500" />
                <p class="text-xs text-zinc-500">مراجعة القرآن</p>
            </div>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($totals['review']['ayahs']) }} <span class="text-sm font-medium">آية</span></p>
            <p class="text-xs text-zinc-400 mt-1">
                {{ $totals['review']['days'] }} يوم مراجعة
                @if($totals['review']['average'] !== null)
                    · متوسط التقييم {{ $totals['review']['average'] }}/3
                @endif
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex items-center gap-2 mb-1">
                <flux:icon icon="calendar-days" class="size-4 text-violet-500" />
                <p class="text-xs text-zinc-500">الحضور</p>
            </div>
            <p class="text-2xl font-bold text-violet-600 dark:text-violet-400">
                {{ $totals['attendance']['rate'] !== null ? $totals['attendance']['rate'].'%' : '—' }}
            </p>
            <p class="text-xs text-zinc-400 mt-1">
                حاضر {{ $totals['attendance']['present'] }} · متأخر {{ $totals['attendance']['late'] }} · غائب {{ $totals['attendance']['absent'] }} · مستأذن {{ $totals['attendance']['excused'] }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex items-center gap-2 mb-1">
                <flux:icon icon="document-text" class="size-4 text-rose-500" />
                <p class="text-xs text-zinc-500">الأحاديث المحفوظة</p>
            </div>
            <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($totals['hadiths']) }} <span class="text-sm font-medium">حديثاً</span></p>
            <p class="text-xs text-zinc-400 mt-1">ضمن مسارات المتون</p>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
            <div class="flex items-center gap-2 mb-1">
                <flux:icon icon="musical-note" class="size-4 text-amber-500" />
                <p class="text-xs text-zinc-500">أبيات المنظومات</p>
            </div>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($totals['verses']) }} <span class="text-sm font-medium">بيتاً</span></p>
            <p class="text-xs text-zinc-400 mt-1">ضمن مسارات المنظومات</p>
        </div>
    </div>

    {{-- Per-student breakdown --}}
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>الطالب</flux:table.column>
                @if($showCircleColumn)
                    <flux:table.column class="hidden md:table-cell">الحلقة</flux:table.column>
                @endif
                <flux:table.column class="text-center">آيات الحفظ</flux:table.column>
                <flux:table.column class="text-center">آيات المراجعة</flux:table.column>
                <flux:table.column class="text-center">الحضور</flux:table.column>
                <flux:table.column class="text-center">الأحاديث</flux:table.column>
                <flux:table.column class="text-center">الأبيات</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($perStudent as $row)
                    <flux:table.row :key="$row['student']->id">
                        <flux:table.cell class="font-bold text-zinc-900 dark:text-white">{{ $row['student']->name }}</flux:table.cell>
                        @if($showCircleColumn)
                            <flux:table.cell class="hidden md:table-cell">
                                <flux:badge size="sm" variant="neutral">{{ $row['student']->circle?->name ?? 'بدون حلقة' }}</flux:badge>
                            </flux:table.cell>
                        @endif
                        <flux:table.cell class="text-center">
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $row['hifz_ayahs'] }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $row['review_ayahs'] }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            @if($row['attendance_total'] > 0)
                                <flux:badge size="sm" :color="($row['present'] + $row['late']) >= $row['attendance_total'] * 0.8 ? 'green' : (($row['present'] + $row['late']) >= $row['attendance_total'] * 0.5 ? 'amber' : 'red')">
                                    {{ $row['present'] + $row['late'] }} / {{ $row['attendance_total'] }}
                                </flux:badge>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <span class="font-semibold text-rose-600 dark:text-rose-400">{{ $row['hadiths'] }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <span class="font-semibold text-amber-600 dark:text-amber-400">{{ $row['verses'] }}</span>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $showCircleColumn ? 7 : 6 }}" class="text-center py-16">
                            <flux:text class="text-zinc-400">لا يوجد طلاب ضمن النطاق المحدد</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
