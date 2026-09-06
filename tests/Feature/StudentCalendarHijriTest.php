<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\HijriDate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The calendar draws a month of the academy's own reckoning.
 *
 * It used to draw a Gregorian month and put Arabic month names on it, so it
 * began and ended on days nobody here recognises — and the Hijri date appeared
 * only in the panel beside it.
 */
beforeEach(function () {
    // 21 Rabi' al-Awwal 1448, which falls inside a Hijri month running from
    // 14 August to 11 September — across two Gregorian months.
    Carbon::setTestNow('2026-09-03 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);
});

it('opens on the Hijri month, not the Gregorian one', function () {
    Livewire::actingAs($this->student, 'student')
        ->test('student.calendar')
        ->assertSet('year', 1448)
        ->assertSet('month', 3);
});

it('heads the grid with the Hijri month and the Gregorian months it spans', function () {
    $html = $this->actingAs($this->student, 'student')->get('/student/calendar')->assertOk()->getContent();

    expect(strip_tags($html))->toContain('ربيع الأول ١٤٤٨')
        ->and(strip_tags($html))->toContain('2026-08 — 2026-09')
        // The Gregorian month name it used to be headed with is gone.
        ->and(strip_tags($html))->not->toContain('سبتمبر 2026');
});

it('numbers the squares by the Hijri day, with the Gregorian one beneath', function () {
    $html = $this->actingAs($this->student, 'student')->get('/student/calendar')->assertOk()->getContent();
    $text = preg_replace('/\s+/u', ' ', strip_tags($html));

    // The month's first day is 1, and it is the 14th of August.
    expect($text)->toContain('1 08-14')
        // Today is the 21st of the Hijri month, the 3rd of September.
        ->and($text)->toContain('21 09-03')
        // And it ends on 29, the 11th of September.
        ->and($text)->toContain('29 09-11');
});

it('steps a Hijri month at a time', function () {
    $component = Livewire::actingAs($this->student, 'student')->test('student.calendar');

    $component->call('previousMonth')->assertSet('month', 2)->assertSet('year', 1448);
    $component->call('nextMonth')->call('nextMonth')->assertSet('month', 4);
});

it('crosses the Hijri new year without losing the month', function () {
    $component = Livewire::actingAs($this->student, 'student')->test('student.calendar')
        ->set('year', 1448)
        ->set('month', 1);

    $component->call('previousMonth')
        ->assertSet('year', 1447)
        ->assertSet('month', 12);
});

it('marks activity that falls in the half of the month before the Gregorian one turns', function () {
    // The trap in reading activity by Gregorian month: this day is inside the
    // Hijri month on view, but in the Gregorian month before it.
    Attendance::create([
        'student_id' => $this->student->id,
        'circle_id' => $this->circle->id,
        'teacher_id' => Teacher::factory()->create()->id,
        'date' => '2026-08-20',
        'status' => 'present',
    ]);

    $activity = Livewire::actingAs($this->student, 'student')
        ->test('student.calendar')
        ->viewData('activityDates');

    expect($activity)->toContain('2026-08-20');
});

it('lays the month out from Saturday', function () {
    // 1 Rabi' al-Awwal 1448 is a Friday, so six squares come before it.
    $grid = HijriDate::monthGrid(1448, 3);

    expect($grid['leadingBlanks'])->toBe(6)
        ->and($grid['days'])->toHaveCount(29)
        ->and($grid['first'])->toBe('2026-08-14')
        ->and($grid['last'])->toBe('2026-09-11');
});
