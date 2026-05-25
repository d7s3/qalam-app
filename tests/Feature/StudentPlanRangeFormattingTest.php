<?php

use App\Models\Ayah;
use App\Models\StudentPlanDay;
use App\Models\Surah;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create Surahs
    $this->fatihah = Surah::create([
        'id' => 1,
        'number' => 1,
        'name_arabic' => 'الفاتحة',
        'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah',
        'revelation_order' => 1,
        'verses_count' => 7,
        'start_page' => 1,
        'end_page' => 1,
    ]);

    $this->baqarah = Surah::create([
        'id' => 2,
        'number' => 2,
        'name_arabic' => 'البقرة',
        'name_simple' => 'Al-Baqarah',
        'revelation_place' => 'madinah',
        'revelation_order' => 87,
        'verses_count' => 286,
        'start_page' => 2,
        'end_page' => 49,
    ]);

    $this->ghafir = Surah::create([
        'id' => 40,
        'number' => 40,
        'name_arabic' => 'غافر',
        'name_simple' => 'Ghafir',
        'revelation_place' => 'makkah',
        'revelation_order' => 60,
        'verses_count' => 85,
        'start_page' => 467,
        'end_page' => 476,
    ]);

    $this->fussilat = Surah::create([
        'id' => 41,
        'number' => 41,
        'name_arabic' => 'فصلت',
        'name_simple' => 'Fussilat',
        'revelation_place' => 'makkah',
        'revelation_order' => 61,
        'verses_count' => 54,
        'start_page' => 477,
        'end_page' => 482,
    ]);

    $this->inshiqaq = Surah::create([
        'id' => 84,
        'number' => 84,
        'name_arabic' => 'الانشقاق',
        'name_simple' => 'Al-Inshiqaq',
        'revelation_place' => 'makkah',
        'revelation_order' => 83,
        'verses_count' => 25,
        'start_page' => 589,
        'end_page' => 589,
    ]);

    $this->nas = Surah::create([
        'id' => 114,
        'number' => 114,
        'name_arabic' => 'الناس',
        'name_simple' => 'Al-Nas',
        'revelation_place' => 'makkah',
        'revelation_order' => 21,
        'verses_count' => 6,
        'start_page' => 604,
        'end_page' => 604,
    ]);

    // Create a helper function to create ayahs on-the-fly or we can just model mock them if they are loaded.
    // Actually, StudentPlanDay->formatRange() accesses $from->surah, $from->verse_number, etc.
    // So we need real DB records or relations loaded. Let's create the required ayahs.
});

function createAyah($id, $surahId, $verseNumber)
{
    return Ayah::create([
        'id' => $id,
        'surah_id' => $surahId,
        'verse_number' => $verseNumber,
        'verse_key' => "$surahId:$verseNumber",
        'juz_number' => 1,
        'hizb_number' => 1,
        'rub_number' => 1,
        'page_number' => 1,
        'ruku_number' => 1,
        'manzil_number' => 1,
        'text_uthmani' => "Ayah $verseNumber text",
        'line_number_start' => 1,
        'line_number_end' => 1,
    ]);
}

test('formats single surah full range correctly', function () {
    $from = createAyah(1, 1, 1); // Al-Fatihah 1
    $to = createAyah(2, 1, 7); // Al-Fatihah 7 (last verse)

    $day = new StudentPlanDay([
        'from_ayah_id' => $from->id,
        'to_ayah_id' => $to->id,
    ]);
    $day->setRelation('fromAyah', $from);
    $day->setRelation('toAyah', $to);

    expect($day->formatRange('hifz'))->toBe('الفاتحة');
});

test('formats single surah partial range correctly', function () {
    $from = createAyah(3, 1, 2); // Al-Fatihah 2
    $to = createAyah(4, 1, 5); // Al-Fatihah 5

    $day = new StudentPlanDay([
        'from_ayah_id' => $from->id,
        'to_ayah_id' => $to->id,
    ]);
    $day->setRelation('fromAyah', $from);
    $day->setRelation('toAyah', $to);

    expect($day->formatRange('hifz'))->toBe('الفاتحة 2-5');
});

test('formats multi-surah full range correctly', function () {
    $from = createAyah(5, 1, 1); // Al-Fatihah 1
    $to = createAyah(6, 2, 286); // Al-Baqarah 286

    $day = new StudentPlanDay([
        'from_ayah_id' => $from->id,
        'to_ayah_id' => $to->id,
    ]);
    $day->setRelation('fromAyah', $from);
    $day->setRelation('toAyah', $to);

    expect($day->formatRange('hifz'))->toBe('الفاتحة - البقرة');
});

test('formats multi-surah range with omitted start/end verse numbers correctly (User Example 1)', function () {
    // فصلت 54-20 و غافر 84-1
    // from Fussilat verse 20 to Ghafir verse 84
    $from = createAyah(7, 41, 20); // Fussilat 20
    $to = createAyah(8, 40, 84); // Ghafir 84

    $day = new StudentPlanDay([
        'from_ayah_id' => $from->id,
        'to_ayah_id' => $to->id,
    ]);
    $day->setRelation('fromAyah', $from);
    $day->setRelation('toAyah', $to);

    expect($day->formatRange('hifz'))->toBe('فصلت 20 الى غافر 84');
});

test('formats multi-surah range from first verse to partial end correctly (User Example 2)', function () {
    // الناس الى الانشقاق 13
    $from = createAyah(9, 114, 1); // Nas 1
    $to = createAyah(10, 84, 13); // Inshiqaq 13

    $day = new StudentPlanDay([
        'from_ayah_id' => $from->id,
        'to_ayah_id' => $to->id,
    ]);
    $day->setRelation('fromAyah', $from);
    $day->setRelation('toAyah', $to);

    expect($day->formatRange('hifz'))->toBe('الناس الى الانشقاق 13');
});
