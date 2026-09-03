@props([
    'date',
    /** full | weekday | weekdayOnly | dayMonth | monthYear | withTime, or an ICU pattern. */
    'style' => 'full',
    /** Shown when the date is missing, so a table cell never sits empty. */
    'fallback' => '—',
    /**
     * Whether the Gregorian date rides alongside.
     *
     * On by default: the academy reckons by the Hijri calendar, but nearly
     * everything it deals with outside — a school term, a bank, a parent's
     * phone — reckons by the other, and reading a date twice is slower than
     * seeing both at once. Turn it off only where the second date would crowd
     * out the first.
     */
    'gregorian' => true,
])

@php
    use App\Support\HijriDate;

    $hijriText = match ($style) {
        'weekday' => HijriDate::withWeekday($date),
        'weekdayOnly' => HijriDate::weekday($date),
        'dayMonth' => HijriDate::dayMonth($date),
        'monthYear' => HijriDate::monthYear($date),
        'withTime' => HijriDate::withTime($date),
        'full' => HijriDate::full($date),
        default => HijriDate::format($date, $style),
    };

    // A weekday names no date, and a month needs no day beside it. Both are
    // taken from the Gregorian reading — `HijriDate::format()` would give the
    // Hijri year and month back again, which says nothing new.
    $gregorianDate = HijriDate::gregorian($date);

    $companion = match (true) {
        $style === 'weekdayOnly' => '',
        $style === 'monthYear' => substr($gregorianDate, 0, 7),
        default => $gregorianDate,
    };

    $showsCompanion = $gregorian && $companion !== '';
@endphp

@if ($hijriText === '')
    <span {{ $attributes }}>{{ $fallback }}</span>
@elseif ($showsCompanion)
    <span {{ $attributes->merge(['class' => 'inline-flex items-baseline gap-1.5']) }}>
        <span>{{ $hijriText }}</span>
        <span dir="ltr" class="text-[0.78em] font-medium tabular-nums text-zinc-500 dark:text-zinc-400 whitespace-nowrap">{{ $companion }}</span>
    </span>
@else
    <span {{ $attributes }}>{{ $hijriText }}</span>
@endif
