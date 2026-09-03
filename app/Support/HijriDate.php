<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use IntlCalendar;
use IntlDateFormatter;

/**
 * Dates as the academy reads them: Umm al-Qura Hijri, in Arabic.
 *
 * The calendar and locale used to be spelled out wherever a date was shown —
 * eighty hand-built formatters across twenty-six files, drifting between
 * patterns. They all come through here now, so a date reads the same wherever
 * it appears and a change of wording is made once.
 *
 * Dates are stored and reasoned about in Gregorian throughout; this is a
 * reading of them, never a replacement.
 */
class HijriDate
{
    private const LOCALE = 'ar_SA@calendar=islamic-umalqura';

    private const TIMEZONE = 'Asia/Riyadh';

    /**
     * Formatters are expensive to build and are asked for the same handful of
     * patterns over and over in a table, so each pattern is built once.
     *
     * @var array<string, IntlDateFormatter>
     */
    private static array $formatters = [];

    /**
     * "١٨ صفر ١٤٤٨" — the everyday form.
     */
    public static function full(DateTimeInterface|string|int|null $date): string
    {
        return self::format($date, 'd MMMM yyyy');
    }

    /**
     * "السبت، ١٨ صفر ١٤٤٨" — when the day of the week matters.
     */
    public static function withWeekday(DateTimeInterface|string|int|null $date): string
    {
        return self::format($date, 'EEEE، d MMMM yyyy');
    }

    /**
     * "١٨ صفر" — inside a table or a chart, where the year is already known.
     */
    public static function dayMonth(DateTimeInterface|string|int|null $date): string
    {
        return self::format($date, 'd MMMM');
    }

    /**
     * "صفر ١٤٤٨" — a heading over a month.
     */
    public static function monthYear(DateTimeInterface|string|int|null $date): string
    {
        return self::format($date, 'MMMM yyyy');
    }

    /**
     * "السبت".
     */
    public static function weekday(DateTimeInterface|string|int|null $date): string
    {
        return self::format($date, 'EEEE');
    }

    /**
     * "١٨ صفر ١٤٤٨ ٠٥:٣٠ م" — when the hour is part of the record.
     */
    public static function withTime(DateTimeInterface|string|int|null $date): string
    {
        return self::format($date, 'd MMMM yyyy hh:mm a');
    }

    /**
     * The Gregorian date behind a Hijri one, for the title attribute that
     * carries it on hover.
     */
    public static function gregorian(DateTimeInterface|string|int|null $date): string
    {
        $timestamp = self::timestamp($date);

        return $timestamp === null ? '' : Carbon::createFromTimestamp($timestamp, self::TIMEZONE)->format('Y-m-d');
    }

    /**
     * The Hijri date with the Gregorian one after it, as plain text.
     *
     * For the places that cannot hold markup and so cannot use the
     * `<x-hijri-date>` component: an option inside a select, a printed sheet, a
     * message sent to a parent, a title attribute. The reading is the same one
     * the component shows, so the two never tell a person different things.
     */
    public static function withGregorian(
        DateTimeInterface|string|int|null $date,
        string $style = 'full',
    ): string {
        $hijri = match ($style) {
            'weekday' => self::withWeekday($date),
            'dayMonth' => self::dayMonth($date),
            'monthYear' => self::monthYear($date),
            'withTime' => self::withTime($date),
            default => self::full($date),
        };

        if ($hijri === '') {
            return '';
        }

        $gregorian = self::gregorian($date);

        if ($style === 'monthYear') {
            $gregorian = substr($gregorian, 0, 7);
        }

        return $gregorian === '' ? $hijri : "{$hijri} ({$gregorian})";
    }

    /**
     * A Hijri month laid out for a calendar grid.
     *
     * The academy reckons by this calendar, so a month of it is what a calendar
     * should draw — not a Gregorian month with Hijri labels, which shows a month
     * that begins and ends on days nobody here recognises.
     *
     * Each day carries the Gregorian date behind it, because everything stored
     * — attendance, plan days, exams — is stored by that one.
     *
     * @return array{year: int, month: int, label: string, span: string, leadingBlanks: int, first: string, last: string, days: array<int, array{hijri: int, date: string}>}
     */
    public static function monthGrid(?int $year = null, ?int $month = null): array
    {
        $calendar = self::calendar();

        if ($year !== null && $month !== null) {
            $calendar->set(IntlCalendar::FIELD_YEAR, $year);
            // ICU counts months from zero; everything above and below counts
            // from one, as a person would.
            $calendar->set(IntlCalendar::FIELD_MONTH, $month - 1);
        }

        $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);

        $year = $calendar->get(IntlCalendar::FIELD_YEAR);
        $month = $calendar->get(IntlCalendar::FIELD_MONTH) + 1;
        $length = $calendar->getActualMaximum(IntlCalendar::FIELD_DAY_OF_MONTH);

        // The week here starts on Saturday, so Saturday must sit in the first
        // column: ICU numbers Sunday 1 through Saturday 7.
        $firstWeekday = $calendar->get(IntlCalendar::FIELD_DAY_OF_WEEK);
        $leadingBlanks = $firstWeekday % 7;

        $days = [];

        for ($day = 1; $day <= $length; $day++) {
            $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, $day);

            $days[] = [
                'hijri' => $day,
                'date' => Carbon::createFromTimestamp((int) ($calendar->getTime() / 1000), self::TIMEZONE)->toDateString(),
            ];
        }

        $first = $days[0]['date'];
        $last = $days[$length - 1]['date'];

        return [
            'year' => $year,
            'month' => $month,
            'label' => self::monthYear($first),
            // The Gregorian months this one runs across, since it rarely sits
            // inside just one.
            'span' => substr($first, 0, 7) === substr($last, 0, 7)
                ? substr($first, 0, 7)
                : substr($first, 0, 7).' — '.substr($last, 0, 7),
            'leadingBlanks' => $leadingBlanks,
            'first' => $first,
            'last' => $last,
            'days' => $days,
        ];
    }

    /**
     * The Hijri month a number of months away from another.
     *
     * @return array{year: int, month: int}
     */
    public static function shiftMonth(int $year, int $month, int $months): array
    {
        $calendar = self::calendar();
        $calendar->set(IntlCalendar::FIELD_YEAR, $year);
        $calendar->set(IntlCalendar::FIELD_MONTH, $month - 1);
        $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
        $calendar->add(IntlCalendar::FIELD_MONTH, $months);

        return [
            'year' => $calendar->get(IntlCalendar::FIELD_YEAR),
            'month' => $calendar->get(IntlCalendar::FIELD_MONTH) + 1,
        ];
    }

    /**
     * The Hijri year and month a Gregorian date falls in.
     *
     * @return array{year: int, month: int}
     */
    public static function yearMonthOf(DateTimeInterface|string|int|null $date): array
    {
        $calendar = self::calendar();
        $timestamp = self::timestamp($date);

        if ($timestamp !== null) {
            $calendar->setTime($timestamp * 1000);
        }

        return [
            'year' => $calendar->get(IntlCalendar::FIELD_YEAR),
            'month' => $calendar->get(IntlCalendar::FIELD_MONTH) + 1,
        ];
    }

    private static function calendar(): IntlCalendar
    {
        return IntlCalendar::createInstance(self::TIMEZONE, self::LOCALE);
    }

    /**
     * Format against any ICU pattern. An empty date reads as an empty string,
     * so a view never has to guard a missing one.
     */
    public static function format(DateTimeInterface|string|int|null $date, string $pattern): string
    {
        $timestamp = self::timestamp($date);

        if ($timestamp === null) {
            return '';
        }

        self::$formatters[$pattern] ??= new IntlDateFormatter(
            self::LOCALE,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            self::TIMEZONE,
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );

        $formatted = self::$formatters[$pattern]->format($timestamp);

        return $formatted === false ? '' : $formatted;
    }

    /**
     * Anything a date can arrive as, reduced to a timestamp. Blank strings and
     * unparseable values give null rather than today, so a missing date is
     * never quietly rendered as one.
     */
    private static function timestamp(DateTimeInterface|string|int|null $date): ?int
    {
        if ($date === null || $date === '') {
            return null;
        }

        if (is_int($date)) {
            return $date;
        }

        if ($date instanceof DateTimeInterface) {
            return $date->getTimestamp();
        }

        try {
            return Carbon::parse($date)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }
}
