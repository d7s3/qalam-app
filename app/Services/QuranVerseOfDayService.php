<?php

namespace App\Services;

use App\Models\Ayah;
use Illuminate\Support\Carbon;

class QuranVerseOfDayService
{
    /**
     * Deterministic ayah for today: every student sees the same verse, and it
     * changes at midnight. No curated list yet — picks anywhere across the
     * mushaf (1-6236), seeded by today's date. Falls back to any available
     * ayah when the full mushaf isn't seeded (e.g. narrow test fixtures), and
     * to null when there is no ayah data at all.
     */
    public static function today(): ?Ayah
    {
        $seed = (int) Carbon::today()->format('Ymd');
        $ayahId = ($seed % 6236) + 1;

        return Ayah::with('surah')->find($ayahId)
            ?? Ayah::with('surah')->inRandomOrder()->first();
    }
}
