<?php

namespace App\Support;

use App\Models\StudentPlanDay;
use App\Models\Surah;
use Illuminate\Support\Collection;

/**
 * Shapes a Quran plan day for the tasmeeh card.
 *
 * The card renders its days in the browser from this data rather than from
 * server-rendered markup — twenty-five days weigh a few kilobytes here against
 * half a megabyte as HTML. Both the JSON endpoint and the card itself shape
 * days through this one place, so the two can never drift apart on how a range
 * reads or where a link points.
 */
class TasmeehPayload
{
    /**
     * @param  Collection<int, StudentPlanDay>  $days
     * @return array<int, array<string, mixed>>
     */
    public static function quranDays(Collection $days): array
    {
        return $days->map(fn (StudentPlanDay $day) => [
            'id' => $day->id,
            'date' => $day->date?->format('Y-m-d'),
            'day_name' => $day->day_name,
            'hifz' => [
                'range' => $day->formatRange('hifz', false),
                'achievement' => $day->hifz_achievement,
                'links' => self::quranLinks($day->fromAyah, $day->toAyah),
            ],
            'review' => [
                'range' => $day->formatRange('review', false),
                'achievement' => $day->review_achievement,
                'links' => self::quranLinks($day->reviewFromAyah, $day->reviewToAyah),
            ],
        ])->values()->all();
    }

    /**
     * Quran.com links for a range, one per surah it spans.
     *
     * @return array<int, array{name: string, url: string}>
     */
    public static function quranLinks($from, $to): array
    {
        if (! $from) {
            return [];
        }

        if (! $to || $from->surah_id === $to->surah_id) {
            $last = $to?->verse_number ?? $from->surah->verses_count;

            return [[
                'name' => $from->surah->name_arabic,
                'url' => 'https://quran.com/ar/'.$from->surah->number.'/'.$from->verse_number.'-'.$last,
            ]];
        }

        $surahs = Surah::whereBetween('id', [
            min($from->surah_id, $to->surah_id),
            max($from->surah_id, $to->surah_id),
        ])->orderBy('id', $from->surah_id <= $to->surah_id ? 'asc' : 'desc')->get();

        return $surahs->map(function (Surah $surah) use ($from, $to) {
            $start = $surah->id === $from->surah_id ? $from->verse_number : 1;
            $end = $surah->id === $to->surah_id ? $to->verse_number : $surah->verses_count;

            return [
                'name' => $surah->name_arabic,
                'url' => 'https://quran.com/ar/'.$surah->number.'/'.$start.'-'.$end,
            ];
        })->values()->all();
    }
}
