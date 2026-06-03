<?php

use App\Models\Ayah;
use App\Models\Surah;
use App\Services\QuranPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Surah::create([
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

    // Create 15 ayahs for page 1, each on a different line
    for ($i = 1; $i <= 15; $i++) {
        Ayah::create([
            'id' => $i,
            'surah_id' => 1,
            'verse_number' => $i,
            'page_number' => 1,
            'line_number_start' => $i,
            'line_number_end' => $i,
            'verse_key' => "1:$i",
            'juz_number' => 1,
            'hizb_number' => 1,
            'rub_number' => 1,
            'ruku_number' => 1,
            'manzil_number' => 1,
            'text_uthmani' => "Ayah $i text",
        ]);
    }
});

it('calculates full page end correctly', function () {
    $service = new QuranPlanService;
    $start = Ayah::find(1);

    $end = $service->getEndAyah($start, 'page');

    expect($end->line_number_end)->toBe(15);
});

it('calculates half page end correctly at line 8', function () {
    $service = new QuranPlanService;
    $start = Ayah::find(1);

    $end = $service->getEndAyah($start, 'half');

    expect($end->line_number_end)->toBe(8);
});

it('calculates third page end correctly at line 5', function () {
    $service = new QuranPlanService;
    $start = Ayah::find(1);

    $end = $service->getEndAyah($start, 'third');

    expect($end->line_number_end)->toBe(5);
});

it('finds next start ayah correctly', function () {
    $service = new QuranPlanService;
    $start = Ayah::find(1);
    $end = Ayah::find(5);

    $next = $service->getNextStartAyah($start, $end, 'page');

    expect($next->id)->toBe(6);
});

it('calculates juz end from An-Naba 1 to An-Nas last verse correctly', function () {
    // Create Surah 78 (An-Naba)
    Surah::create([
        'id' => 78,
        'number' => 78,
        'name_arabic' => 'النبأ',
        'name_simple' => 'An-Naba',
        'revelation_place' => 'makkah',
        'revelation_order' => 80,
        'verses_count' => 40,
        'start_page' => 582,
        'end_page' => 583,
    ]);

    $start = Ayah::create([
        'id' => 78001,
        'surah_id' => 78,
        'verse_number' => 1,
        'page_number' => 582,
        'line_number_start' => 1,
        'line_number_end' => 1,
        'verse_key' => '78:1',
        'juz_number' => 30,
        'hizb_number' => 59,
        'rub_number' => 1,
        'ruku_number' => 1,
        'manzil_number' => 7,
        'text_uthmani' => 'An-Naba Ayah 1',
    ]);

    // Create Surah 114 (An-Nas)
    Surah::create([
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

    // We only need the last ayah of Surah 114 for the query
    $lastAyah = Ayah::create([
        'id' => 114006,
        'surah_id' => 114,
        'verse_number' => 6,
        'page_number' => 604,
        'line_number_start' => 15,
        'line_number_end' => 15,
        'verse_key' => '114:6',
        'juz_number' => 30,
        'hizb_number' => 60,
        'rub_number' => 4,
        'ruku_number' => 1,
        'manzil_number' => 7,
        'text_uthmani' => 'An-Nas Ayah 6',
    ]);

    $service = new QuranPlanService;
    $end = $service->getEndAyah($start, 'juz', 'forward');

    expect($end->id)->toBe($lastAyah->id);
});
