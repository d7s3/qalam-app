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

it('keeps the Gregorian reading of a date', function () {
    expect(HijriDate::gregorian('2026-08-01'))->toBe('2026-08-01');
});

it('renders the Hijri date with the Gregorian one visible beside it', function () {
    // The Gregorian date used to ride in the title, seen only on hover. The
    // academy reckons in Hijri, but nearly everything it deals with outside —
    // a term, a bank, a parent's phone — reckons by the other, and reading a
    // date twice is slower than seeing both at once.
    $html = Blade::render('<x-hijri-date :date="$date" />', ['date' => '2026-08-01']);

    expect($html)->toContain('١٨ صفر ١٤٤٨')
        ->and($html)->toContain('2026-08-01')
        ->and(strip_tags($html))->toContain('2026-08-01');
});

it('sets the second date aside rather than letting it crowd the first', function () {
    $html = Blade::render('<x-hijri-date :date="$date" />', ['date' => '2026-08-01']);

    expect($html)->toContain('dir="ltr"')
        ->and($html)->toContain('tabular-nums');
});

it('drops the second date when it is asked to', function () {
    $html = Blade::render('<x-hijri-date :date="$date" :gregorian="false" />', ['date' => '2026-08-01']);

    expect($html)->toContain('١٨ صفر ١٤٤٨')
        ->and($html)->not->toContain('2026-08-01');
});

it('says no Gregorian date beside a weekday, which names none', function () {
    $html = Blade::render('<x-hijri-date :date="$date" style="weekdayOnly" />', ['date' => '2026-08-01']);

    expect($html)->not->toContain('2026-08-01');
});

it('pairs a Hijri month with a Gregorian month, not a whole date', function () {
    $html = Blade::render('<x-hijri-date :date="$date" style="monthYear" />', ['date' => '2026-08-01']);

    expect(strip_tags($html))->toContain('2026-08')
        ->and(strip_tags($html))->not->toContain('2026-08-01');
});

it('falls back rather than showing an empty cell', function () {
    $html = Blade::render('<x-hijri-date :date="null" />');

    expect(trim(strip_tags($html)))->toBe('—');
});

it('takes an ICU pattern of its own when the named styles do not fit', function () {
    $html = Blade::render('<x-hijri-date :date="$date" style="MMMM" />', ['date' => '2026-08-01']);

    expect(strip_tags($html))->toContain('صفر')
        ->and(strip_tags($html))->toContain('2026-08-01');
});

it('gives the same reading as plain text where markup cannot go', function () {
    // A select option, a printed sheet, a message to a parent: the two must
    // never tell a person different things.
    expect(HijriDate::withGregorian('2026-08-01'))->toBe('١٨ صفر ١٤٤٨ (2026-08-01)')
        ->and(HijriDate::withGregorian('2026-08-01', 'monthYear'))->toBe('صفر ١٤٤٨ (2026-08)')
        ->and(HijriDate::withGregorian(null))->toBe('');
});

it('shows dates in Hijri on the manager pages, Gregorian only on hover', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $stage = Stage::factory()->create();
    Circle::factory()->create(['stage_id' => $stage->id]);
    Guardian::factory()->create();

    $this->actingAs(Manager::factory()->create(), 'manager');

    foreach (['/manager/stages', '/manager/circles', '/manager/guardians'] as $page) {
        $html = $this->get($page)->assertOk()->getContent();

        // The Hijri date leads, and the Gregorian one is readable beside it
        // rather than hidden behind a hover nobody finds on a phone.
        expect($html)->toContain('١٨ صفر ١٤٤٨')
            ->and(strip_tags($html))->toContain('2026-08-01');
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
