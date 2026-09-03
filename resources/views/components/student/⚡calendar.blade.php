<?php

use App\Models\Attendance;
use App\Models\StudentExam;
use App\Models\StudentPlanDay;
use App\Services\MemorizationJourneyService;
use App\Support\HijriDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    /** The Hijri month on view — the academy's own month, not a Gregorian one. */
    public int $month;

    public int $year;

    public ?string $selectedDate = null;

    public function mount(): void
    {
        ['year' => $this->year, 'month' => $this->month] = HijriDate::yearMonthOf(now());
        $this->selectedDate = now()->toDateString();
    }

    public function previousMonth(): void
    {
        ['year' => $this->year, 'month' => $this->month] = HijriDate::shiftMonth($this->year, $this->month, -1);
    }

    public function nextMonth(): void
    {
        ['year' => $this->year, 'month' => $this->month] = HijriDate::shiftMonth($this->year, $this->month, 1);
    }

    public function selectDay(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function with(): array
    {
        $student = Auth::guard('student')->user();
        $grid = HijriDate::monthGrid($this->year, $this->month);

        // A Hijri month begins and ends in the middle of two Gregorian ones, so
        // the activity is asked for over its span rather than for a month.
        $activityDates = MemorizationJourneyService::activityDatesBetween(
            $student,
            Carbon::parse($grid['first'])->startOfDay(),
            Carbon::parse($grid['last'])->endOfDay(),
        );

        $dayDetail = null;
        if ($this->selectedDate) {
            $attendance = Attendance::where('student_id', $student->id)->whereDate('date', $this->selectedDate)->first();

            $planDays = StudentPlanDay::with(['fromAyah.surah', 'toAyah.surah', 'reviewFromAyah.surah', 'reviewToAyah.surah'])
                ->whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
                ->whereDate('date', $this->selectedDate)
                ->get();

            $exams = StudentExam::with('examLevel')
                ->where('student_id', $student->id)
                ->whereDate('date_time', $this->selectedDate)
                ->get();

            $dayDetail = [
                'attendance' => $attendance,
                'planDays' => $planDays,
                'exams' => $exams,
            ];
        }

        return [
            'grid' => $grid,
            'activityDates' => $activityDates,
            'todayStr' => now()->toDateString(),
            'dayDetail' => $dayDetail,
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
            {{ __('التقويم') }}
        </flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('اضغط على أي يوم لعرض تفاصيله: الحضور، الحفظ، المراجعة، والاختبارات.') }}
        </flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-6 items-start">
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <button wire:click="previousMonth" class="p-2 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <flux:icon icon="chevron-right" class="size-5 text-zinc-400" />
                </button>
                <span class="flex flex-col items-center leading-tight">
                    <span class="font-bold text-lg text-zinc-800 dark:text-zinc-100">{{ $grid['label'] }}</span>
                    <span dir="ltr" class="text-[11px] font-medium tabular-nums text-zinc-400">{{ $grid['span'] }}</span>
                </span>
                <button wire:click="nextMonth" class="p-2 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <flux:icon icon="chevron-left" class="size-5 text-zinc-400" />
                </button>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-xs text-zinc-400 mb-2">
                @foreach(['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'] as $dayLabel)
                    <div>{{ $dayLabel }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-sm">
                @for($i = 0; $i < $grid['leadingBlanks']; $i++)
                    <div></div>
                @endfor
                @foreach($grid['days'] as $cell)
                    @php
                        $dateStr = $cell['date'];
                        $isToday = $dateStr === $todayStr;
                        $isSelected = $dateStr === $selectedDate;
                        $hasActivity = in_array($dateStr, $activityDates, true);
                    @endphp
                    <button
                        wire:click="selectDay('{{ $dateStr }}')"
                        wire:key="day-{{ $dateStr }}"
                        class="relative flex flex-col items-center justify-center h-14 rounded-lg transition-colors leading-none
                            {{ $isSelected ? 'bg-maroon text-white font-bold' : ($isToday ? 'bg-maroon/10 text-maroon dark:text-red-secondary font-bold' : 'text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800') }}">
                        <span>{{ $cell['hijri'] }}</span>
                        {{-- The Gregorian day of the same square, for anyone reckoning by it --}}
                        <span dir="ltr" class="mt-1 text-[10px] font-medium tabular-nums {{ $isSelected ? 'text-white/70' : 'text-zinc-400 dark:text-zinc-500' }}">
                            {{ substr($dateStr, 5) }}
                        </span>
                        @if($hasActivity && !$isSelected)
                            <span class="absolute bottom-0.5 size-1 rounded-full bg-emerald-500"></span>
                        @endif
                    </button>
                @endforeach
            </div>
        </flux:card>

        <flux:card>
            @if($selectedDate)
                <flux:heading size="sm" class="mb-4">
                    <x-hijri-date :date="\Carbon\Carbon::parse($selectedDate)" style="weekday" />
                </flux:heading>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('الحضور') }}</span>
                        @php
                            $attStatus = $dayDetail['attendance']?->status;
                            $attDetails = match($attStatus) {
                                'present' => ['color' => 'emerald', 'label' => 'حاضر'],
                                'absent' => ['color' => 'red', 'label' => 'غائب'],
                                'late' => ['color' => 'amber', 'label' => 'متأخر'],
                                'excused' => ['color' => 'blue', 'label' => 'مستأذن'],
                                default => ['color' => 'zinc', 'label' => 'غير مسجل'],
                            };
                        @endphp
                        <flux:badge :color="$attDetails['color']" size="sm">{{ __($attDetails['label']) }}</flux:badge>
                    </div>

                    @forelse($dayDetail['planDays'] as $planDay)
                        @if($planDay->fromAyah && $planDay->toAyah)
                            <div class="flex items-center justify-between border-t border-zinc-50 dark:border-zinc-800/60 pt-3">
                                <div>
                                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ __('حفظ') }}</div>
                                    <div class="text-xs text-zinc-400">{{ $planDay->formatRange('hifz') }}</div>
                                </div>
                                @php
                                    $g = match($planDay->hifz_achievement) {
                                        3 => ['color' => 'emerald', 'label' => 'ممتاز'], 2 => ['color' => 'blue', 'label' => 'جيد'],
                                        1 => ['color' => 'amber', 'label' => 'مقبول'], default => ['color' => 'zinc', 'label' => 'لم يُقيَّم'],
                                    };
                                @endphp
                                <flux:badge :color="$g['color']" size="sm">{{ __($g['label']) }}</flux:badge>
                            </div>
                        @endif
                        @if($planDay->reviewFromAyah && $planDay->reviewToAyah)
                            <div class="flex items-center justify-between border-t border-zinc-50 dark:border-zinc-800/60 pt-3">
                                <div>
                                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ __('مراجعة') }}</div>
                                    <div class="text-xs text-zinc-400">{{ $planDay->formatRange('review') }}</div>
                                </div>
                                @php
                                    $g = match($planDay->review_achievement) {
                                        3 => ['color' => 'emerald', 'label' => 'ممتاز'], 2 => ['color' => 'blue', 'label' => 'جيد'],
                                        1 => ['color' => 'amber', 'label' => 'مقبول'], default => ['color' => 'zinc', 'label' => 'لم يُقيَّم'],
                                    };
                                @endphp
                                <flux:badge :color="$g['color']" size="sm">{{ __($g['label']) }}</flux:badge>
                            </div>
                        @endif
                    @empty
                    @endforelse

                    @foreach($dayDetail['exams'] as $exam)
                        <div class="flex items-center justify-between border-t border-zinc-50 dark:border-zinc-800/60 pt-3">
                            <div>
                                <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ __('اختبار') }}</div>
                                <div class="text-xs text-zinc-400">{{ $exam->examLevel?->name }}</div>
                            </div>
                            <flux:badge color="indigo" size="sm">{{ $exam->status }}</flux:badge>
                        </div>
                    @endforeach

                    @if($dayDetail['planDays']->isEmpty() && $dayDetail['exams']->isEmpty() && !$dayDetail['attendance'])
                        <p class="text-sm text-zinc-400 text-center py-6">{{ __('لا يوجد نشاط في هذا اليوم.') }}</p>
                    @endif
                </div>
            @endif
        </flux:card>
    </div>
</div>
