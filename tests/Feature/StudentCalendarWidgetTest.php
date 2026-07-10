<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\MemorizationJourneyService;
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

it('renders the current month by default with today highlighted', function () {
    Livewire::test('student.calendar-widget')
        ->assertSee('يوليو 2026')
        ->assertSet('month', 7)
        ->assertSet('year', 2026);
});

it('navigates to the previous and next month', function () {
    Livewire::test('student.calendar-widget')
        ->call('previousMonth')
        ->assertSet('month', 6)
        ->assertSet('year', 2026)
        ->call('nextMonth')
        ->call('nextMonth')
        ->assertSet('month', 8)
        ->assertSet('year', 2026);
});

it('wraps correctly across a year boundary', function () {
    Livewire::test('student.calendar-widget')
        ->set('month', 1)
        ->set('year', 2026)
        ->call('previousMonth')
        ->assertSet('month', 12)
        ->assertSet('year', 2025);
});

it('marks days with attendance activity', function () {
    Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $this->circle->id,
        'date' => '2026-07-10',
        'status' => 'present',
    ]);

    $dates = MemorizationJourneyService::activityDatesForMonth($this->student, 2026, 7);

    expect($dates)->toContain('2026-07-10');
});
