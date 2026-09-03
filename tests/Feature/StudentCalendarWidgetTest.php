<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\MemorizationJourneyService;
use App\Support\HijriDate;
use Livewire\Livewire;

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-07-15 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    AcademicCalendarEvent::create([
        'event_name' => 'دوام كامل',
        'start_date' => now()->subDays(30)->format('Y-m-d'),
        'end_date' => now()->addDays(30)->format('Y-m-d'),
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5, 6, 7],
        'is_visible' => true,
    ]);

    $this->actingAs($this->student, 'student');
});

it('opens on the current Hijri month, with the Gregorian months it spans', function () {
    // 15 July 2026 falls in Safar 1448, a month running across July and August.
    Livewire::test('student.calendar-widget')
        ->assertSee('صفر ١٤٤٨')
        ->assertSee('2026-07 — 2026-08')
        ->assertSet('month', 2)
        ->assertSet('year', 1448);
});

it('steps a Hijri month at a time', function () {
    Livewire::test('student.calendar-widget')
        ->call('previousMonth')
        ->assertSet('month', 1)
        ->assertSet('year', 1448)
        ->call('nextMonth')
        ->call('nextMonth')
        ->assertSet('month', 3)
        ->assertSet('year', 1448);
});

it('wraps correctly across the Hijri year boundary', function () {
    Livewire::test('student.calendar-widget')
        ->set('month', 1)
        ->set('year', 1448)
        ->call('previousMonth')
        ->assertSet('month', 12)
        ->assertSet('year', 1447);
});

it('marks days with attendance activity', function () {
    Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $this->circle->id,
        // Safar 1448 runs from 15 July to 13 August, so this day sits inside
        // the month on view but in the Gregorian month after it — exactly what
        // reading activity by Gregorian month used to miss.
        'date' => '2026-08-05',
        'status' => 'present',
    ]);

    $grid = HijriDate::monthGrid(1448, 2);
    $dates = MemorizationJourneyService::activityDatesBetween(
        $this->student,
        Carbon\Carbon::parse($grid['first'])->startOfDay(),
        Carbon\Carbon::parse($grid['last'])->endOfDay(),
    );

    expect($dates)->toContain('2026-08-05')
        // And the old reading, by the Gregorian month the day is not in, misses it.
        ->and(MemorizationJourneyService::activityDatesForMonth($this->student, 2026, 7))
        ->not->toContain('2026-08-05');
});
