<?php

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

    public function mount(): void
    {
        ['year' => $this->year, 'month' => $this->month] = HijriDate::yearMonthOf(now());
    }

    public function previousMonth(): void
    {
        ['year' => $this->year, 'month' => $this->month] = HijriDate::shiftMonth($this->year, $this->month, -1);
    }

    public function nextMonth(): void
    {
        ['year' => $this->year, 'month' => $this->month] = HijriDate::shiftMonth($this->year, $this->month, 1);
    }

    public function with(): array
    {
        $student = Auth::guard('student')->user();
        $grid = HijriDate::monthGrid($this->year, $this->month);

        // A Hijri month runs across two Gregorian ones, so the activity is asked
        // for over its span rather than for a month.
        $activityDates = MemorizationJourneyService::activityDatesBetween(
            $student,
            Carbon::parse($grid['first'])->startOfDay(),
            Carbon::parse($grid['last'])->endOfDay(),
        );

        return [
            'grid' => $grid,
            'activityDates' => $activityDates,
            'todayStr' => now()->toDateString(),
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
        <span class="flex flex-col items-center leading-tight">
            <span class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ $grid['label'] }}</span>
            <span dir="ltr" class="text-[9px] font-medium tabular-nums text-zinc-400">{{ $grid['span'] }}</span>
        </span>
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
        @for($i = 0; $i < $grid['leadingBlanks']; $i++)
            <div></div>
        @endfor
        @foreach($grid['days'] as $cell)
            @php
                $dateStr = $cell['date'];
                $isToday = $dateStr === $todayStr;
                $hasActivity = in_array($dateStr, $activityDates, true);
            @endphp
            {{-- The square is too small for a second number, so the Gregorian
                 date rides in the title here rather than crowding it out. --}}
            <div title="{{ $dateStr }}"
                class="relative flex flex-col items-center justify-center h-8 rounded-lg leading-none {{ $isToday ? 'bg-maroon text-white font-bold' : 'text-zinc-600 dark:text-zinc-300' }}">
                <span>{{ $cell['hijri'] }}</span>
                @if($hasActivity && !$isToday)
                    <span class="absolute bottom-0.5 size-1 rounded-full bg-emerald-500"></span>
                @endif
            </div>
        @endforeach
    </div>
</div>
