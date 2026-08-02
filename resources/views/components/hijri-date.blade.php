@props([
    'date',
    /** full | weekday | dayMonth | monthYear | withTime, or an ICU pattern. */
    'style' => 'full',
    /** Shown when the date is missing, so a table cell never sits empty. */
    'fallback' => '—',
])

@php
    use App\Support\HijriDate;

    $hijri = match ($style) {
        'weekday' => HijriDate::withWeekday($date),
        'weekdayOnly' => HijriDate::weekday($date),
        'dayMonth' => HijriDate::dayMonth($date),
        'monthYear' => HijriDate::monthYear($date),
        'withTime' => HijriDate::withTime($date),
        'full' => HijriDate::full($date),
        default => HijriDate::format($date, $style),
    };

    $gregorian = HijriDate::gregorian($date);
@endphp

@if ($hijri === '')
    <span {{ $attributes }}>{{ $fallback }}</span>
@else
    {{-- The Gregorian date rides along in the title, for anyone who needs it. --}}
    <span {{ $attributes->merge(['title' => $gregorian ? $gregorian.' م' : null]) }}>{{ $hijri }}</span>
@endif
