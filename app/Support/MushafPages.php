<?php

namespace App\Support;

use App\Models\Ayah;
use Illuminate\Support\Collection;

/**
 * Where the application turns a span of ayahs into a count of mushaf pages.
 *
 * The knowledge is small — `ayahs.page_number` says which page an ayah sits on —
 * but it is asked for in two shapes. A report weighs thousands of ranges at once
 * and cannot afford a query each, so it takes the whole map for an envelope of
 * ayah ids and reads it in memory. The recitation bridge weighs one day's range
 * as it is graded, and wants an answer rather than a map.
 */
class MushafPages
{
    /**
     * The page number of every ayah in an id range, keyed by ayah id.
     *
     * @return Collection<int, int>
     */
    public static function pageByAyah(int $minAyahId, int $maxAyahId): Collection
    {
        return Ayah::whereBetween('id', [$minAyahId, $maxAyahId])->pluck('page_number', 'id');
    }

    /**
     * How many distinct mushaf pages one range of ayahs touches.
     *
     * A range lying inside a single page counts as that one page, and a range
     * crossing a boundary counts both — a page is counted once however many of
     * its ayahs the range covers.
     */
    public static function inRange(?int $fromAyahId, ?int $toAyahId): int
    {
        if (! $fromAyahId || ! $toAyahId) {
            return 0;
        }

        $min = min($fromAyahId, $toAyahId);
        $max = max($fromAyahId, $toAyahId);

        // Counted by the database: hydrating every ayah of a juz to reach one
        // integer is work the bridge repeats on every graded day.
        return Ayah::whereBetween('id', [$min, $max])->distinct()->count('page_number');
    }
}
