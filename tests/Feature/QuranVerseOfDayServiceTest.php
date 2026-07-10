<?php

use App\Models\Surah;
use App\Services\QuranVerseOfDayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedFullMushaf(): void
{
    $surah = Surah::create([
        'number' => 1,
        'name_arabic' => 'سورة',
        'name_simple' => 'Surah',
        'revelation_place' => 'makkah',
        'revelation_order' => 1,
        'verses_count' => 6236,
        'start_page' => 1,
        'end_page' => 604,
    ]);

    $rows = [];
    foreach (range(1, 6236) as $id) {
        $rows[] = [
            'id' => $id,
            'surah_id' => $surah->id,
            'verse_number' => $id,
            'verse_key' => "1:{$id}",
            'juz_number' => 1,
            'hizb_number' => 1,
            'rub_number' => 1,
            'page_number' => 1,
            'ruku_number' => 1,
            'manzil_number' => 1,
            'text_uthmani' => "نص {$id}",
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('ayahs')->insert($chunk);
    }
}

it('returns the same ayah for repeated calls on the same day', function () {
    seedFullMushaf();
    Carbon\Carbon::setTestNow('2026-07-07 09:00:00');

    $first = QuranVerseOfDayService::today();
    $second = QuranVerseOfDayService::today();

    expect($first->id)->toBe($second->id);
    expect($first->surah)->not->toBeNull();
});

it('falls back to any available ayah when the computed id is not seeded', function () {
    // A narrow fixture — only a handful of ayahs, not the full 1-6236 mushaf.
    $surah = Surah::create([
        'number' => 1,
        'name_arabic' => 'سورة',
        'name_simple' => 'Surah',
        'revelation_place' => 'makkah',
        'revelation_order' => 1,
        'verses_count' => 3,
        'start_page' => 1,
        'end_page' => 1,
    ]);
    DB::table('ayahs')->insert([
        'id' => 5000,
        'surah_id' => $surah->id,
        'verse_number' => 1,
        'verse_key' => '1:1',
        'juz_number' => 1,
        'hizb_number' => 1,
        'rub_number' => 1,
        'page_number' => 1,
        'ruku_number' => 1,
        'manzil_number' => 1,
        'text_uthmani' => 'نص',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $verse = QuranVerseOfDayService::today();

    expect($verse)->not->toBeNull();
    expect($verse->id)->toBe(5000);
});

it('returns null when there are no ayahs at all', function () {
    expect(QuranVerseOfDayService::today())->toBeNull();
});

it('returns a different ayah on a different date', function () {
    seedFullMushaf();

    Carbon\Carbon::setTestNow('2026-07-07 09:00:00');
    $day1 = QuranVerseOfDayService::today();

    Carbon\Carbon::setTestNow('2026-07-08 09:00:00');
    $day2 = QuranVerseOfDayService::today();

    expect($day1->id)->not->toBe($day2->id);
});
