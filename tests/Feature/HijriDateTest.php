<?php

use App\Livewire\Teacher\Attendance;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\HijriDate;
use Illuminate\Support\Carbon;

it('reads a date in Umm al-Qura Hijri', function () {
    expect(HijriDate::full('2026-08-01'))->toBe('١٨ صفر ١٤٤٨')
        ->and(HijriDate::withWeekday('2026-08-01'))->toBe('السبت، ١٨ صفر ١٤٤٨')
        ->and(HijriDate::weekday('2026-08-01'))->toBe('السبت')
        ->and(HijriDate::dayMonth('2026-08-01'))->toBe('١٨ صفر')
        ->and(HijriDate::monthYear('2026-08-01'))->toBe('صفر ١٤٤٨');
});

it('takes a date however it arrives', function () {
    $expected = '١٨ صفر ١٤٤٨';

    expect(HijriDate::full('2026-08-01'))->toBe($expected)
        ->and(HijriDate::full('2026-08-01 00:00:00'))->toBe($expected)
        ->and(HijriDate::full(Carbon::parse('2026-08-01')))->toBe($expected)
        ->and(HijriDate::full(Carbon::parse('2026-08-01')->getTimestamp()))->toBe($expected);
});

/**
 * A view should never have to guard a date that might be missing, and a blank
 * one must never quietly read as today.
 */
it('gives an empty string for a missing or unreadable date', function () {
    expect(HijriDate::full(null))->toBe('')
        ->and(HijriDate::full(''))->toBe('')
        ->and(HijriDate::full('not a date'))->toBe('')
        ->and(HijriDate::gregorian(null))->toBe('');
});

it('keeps the Gregorian date for the hover', function () {
    expect(HijriDate::gregorian('2026-08-01'))->toBe('2026-08-01');
});

it('renders the Hijri date and carries the Gregorian in the title', function () {
    $html = Blade::render('<x-hijri-date :date="$date" />', ['date' => '2026-08-01']);

    expect($html)->toContain('١٨ صفر ١٤٤٨')
        ->and($html)->toContain('title="2026-08-01 م"');
});

it('falls back rather than showing an empty cell', function () {
    $html = Blade::render('<x-hijri-date :date="null" />');

    expect(trim(strip_tags($html)))->toBe('—')
        ->and($html)->not->toContain('title=');
});

it('takes an ICU pattern of its own when the named styles do not fit', function () {
    $html = Blade::render('<x-hijri-date :date="$date" style="MMMM" />', ['date' => '2026-08-01']);

    expect(trim(strip_tags($html)))->toBe('صفر');
});

/**
 * The Gregorian date was showing on screen in a hundred places, each building
 * its own formatter. These guard the two halves of the fix: that a page reads
 * its dates in Hijri, and that the Gregorian still rides along for anyone who
 * needs it.
 */
it('shows dates in Hijri on the manager pages, Gregorian only on hover', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $stage = Stage::factory()->create();
    Circle::factory()->create(['stage_id' => $stage->id]);
    Guardian::factory()->create();

    $this->actingAs(Manager::factory()->create(), 'manager');

    foreach (['/manager/stages', '/manager/circles', '/manager/guardians'] as $page) {
        $html = $this->get($page)->assertOk()->getContent();

        expect($html)->toContain('١٨ صفر ١٤٤٨')
            ->and($html)->toContain('title="2026-08-01 م"');

        // No bare Gregorian date left in the text of the page.
        $text = strip_tags($html);
        expect($text)->not->toMatch('/\b2026-08-01\b/');
    }
});

it('carries the Hijri date into the attendance export', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $circle = Circle::factory()->create();
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($circle->id);
    Student::factory()->create([
        'circle_id' => $circle->id, 'status' => 'active', 'is_approved' => true, 'name' => 'طالب',
    ]);

    $this->actingAs($teacher, 'teacher');

    $component = Livewire\Livewire::test(Attendance::class)
        ->set('selectedCircle', $circle->id)
        ->set('date', '2026-08-01');

    ob_start();
    $component->instance()->exportCsv()->sendContent();
    $body = ob_get_clean();

    expect($body)->toContain('التاريخ')
        ->and($body)->toContain('١٨ صفر ١٤٤٨');
});
