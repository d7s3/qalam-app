<?php

use App\Services\MemorizationJourneyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public int $month;

    public int $year;

    public function mount(): void
    {
        $this->month = (int) now()->format('n');
        $this->year = (int) now()->format('Y');
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->month = (int) $date->format('n');
        $this->year = (int) $date->format('Y');
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->month = (int) $date->format('n');
        $this->year = (int) $date->format('Y');
    }

    protected const ARABIC_MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
        7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    public function with(): array
    {
        $student = Auth::guard('student')->user();
        $activityDates = MemorizationJourneyService::activityDatesForMonth($student, $this->year, $this->month);

        $firstOfMonth = Carbon::create($this->year, $this->month, 1);
        $daysInMonth = $firstOfMonth->daysInMonth;
        // Saturday-first week to match the Arabic calendar convention used elsewhere in the app.
        $leadingBlanks = (int) $firstOfMonth->copy()->startOfWeek(Carbon::SATURDAY)->diffInDays($firstOfMonth);

        return [
            'monthLabel' => self::ARABIC_MONTHS[$this->month].' '.$this->year,
            'daysInMonth' => $daysInMonth,
            'leadingBlanks' => $leadingBlanks,
            'activityDates' => $activityDates,
            'todayStr' => now()->toDateString(),
            'yearMonth' => sprintf('%04d-%02d', $this->year, $this->month),
        ];
    }
};
?>

<div class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5" dir="rtl">
    <flux:heading size="sm" class="flex items-center gap-2 mb-4">
        <flux:icon icon="calendar-days" class="size-4 text-maroon" />
        {{ __('التقويم') }}
    </flux:heading>

    <div class="flex items-center justify-between mb-3">
        <button wire:click="previousMonth" class="p-1 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800">
            <flux:icon icon="chevron-right" class="size-4 text-zinc-400" />
        </button>
        <span class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ $monthLabel }}</span>
        <button wire:click="nextMonth" class="p-1 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800">
            <flux:icon icon="chevron-left" class="size-4 text-zinc-400" />
        </button>
    </div>

    <div class="grid grid-cols-7 gap-1 text-center text-[10px] text-zinc-400 mb-1">
        @foreach(['س', 'ح', 'ن', 'ث', 'ر', 'خ', 'ج'] as $dayLetter)
            <div>{{ $dayLetter }}</div>
        @endforeach
    </div>

    <div class="grid grid-cols-7 gap-1 text-center text-xs">
        @for($i = 0; $i < $leadingBlanks; $i++)
            <div></div>
        @endfor
        @for($day = 1; $day <= $daysInMonth; $day++)
            @php
                $dateStr = $yearMonth.'-'.str_pad($day, 2, '0', STR_PAD_LEFT);
                $isToday = $dateStr === $todayStr;
                $hasActivity = in_array($dateStr, $activityDates, true);
            @endphp
            <div class="relative flex flex-col items-center justify-center h-8 rounded-lg {{ $isToday ? 'bg-maroon text-white font-bold' : 'text-zinc-600 dark:text-zinc-300' }}">
                {{ $day }}
                @if($hasActivity && !$isToday)
                    <span class="absolute bottom-0.5 size-1 rounded-full bg-emerald-500"></span>
                @endif
            </div>
        @endfor
    </div>
</div>
