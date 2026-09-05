<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\OccurrenceAttendance;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Task;
use App\Models\Teacher;
use App\Services\DayAgendaService;
use App\Support\CalendarVisibility;
use App\Support\SelfProgramTrack;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The day, by name. The calendar showed what somebody typed into it, on days
 * that already held the student's hifz, his متن and his ورد — all dated, none
 * of them visible.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->student = Student::factory()->create([
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
    ]);

    $this->assigner = Teacher::factory()->create();

    $this->lesson = AcademicCalendarEvent::create([
        'event_name' => 'درس التفسير',
        'start_date' => '2026-09-06',
        'end_date' => '2026-09-10',
        'weekdays' => [1],
        'stage_ids' => [$this->programme->id],
        'is_attendance_period' => false,
    ]);
});

it('lays the appointment and the work of the day side by side', function () {
    $week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohort->id,
        'program_type' => 'self',
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-10',
    ]);

    SelfProgramItem::create([
        'self_program_week_id' => $week->id,
        'track' => SelfProgramTrack::Maqrou,
        'target_amount' => 20,
        'unit' => 'صفحة',
    ]);

    $agenda = DayAgendaService::forUser($this->student, 'student', '2026-09-06');

    expect($agenda['occurrences'])->toHaveCount(1);
    expect($agenda['occurrences'][0]['event']->event_name)->toBe('درس التفسير');
    expect($agenda['occurrences'][0]['status'])->toBeNull();

    expect($agenda['content'])->toHaveCount(1);
    expect($agenda['content'][0]['detail'])->toContain('20');
});

it('carries his own answer beside the appointment', function () {
    OccurrenceAttendance::create([
        'academic_calendar_event_id' => $this->lesson->id,
        'date' => '2026-09-06',
        'user_id' => $this->student->id,
        'role' => 'student',
        'status' => OccurrenceAttendance::PRESENT,
    ]);

    $agenda = DayAgendaService::forUser($this->student, 'student', '2026-09-06');

    expect($agenda['occurrences'][0]['status'])->toBe('present');
});

it('brings an overdue task onto today rather than leaving it behind', function () {
    // A task left on the day it was promised sits on a page nobody opens again,
    // which is how it stops being chased.
    Task::create([
        'title' => 'مراجعة خطط الطلاب',
        'due_date' => '2026-09-02',
        'status' => 'pending',
        'assigned_to_id' => $this->student->id,
        'assigned_to_type' => Student::class,
        'created_by_id' => $this->assigner->id,
        'created_by_type' => Teacher::class,
    ]);

    Task::create([
        'title' => 'مهمة منتهية',
        'due_date' => '2026-09-02',
        'status' => 'completed',
        'assigned_to_id' => $this->student->id,
        'assigned_to_type' => Student::class,
        'created_by_id' => $this->assigner->id,
        'created_by_type' => Teacher::class,
    ]);

    $agenda = DayAgendaService::forUser($this->student, 'student', '2026-09-06');

    expect($agenda['tasks'])->toHaveCount(1);
    expect($agenda['tasks']->first()->title)->toBe('مراجعة خطط الطلاب');
});

it('gives a teacher his appointments without a student body of work', function () {
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($this->cohort->id);

    $agenda = DayAgendaService::forUser($teacher, 'teacher', '2026-09-06');

    expect($agenda['occurrences'])->toHaveCount(1);
    expect($agenda['content'])->toBe([]);
});

it('shows nothing on a day the appointment does not fall', function () {
    // The seventh is a Monday; the lesson keeps Sundays.
    $agenda = DayAgendaService::forUser($this->student, 'student', '2026-09-07');

    expect($agenda['occurrences'])->toBe([]);
});

it('keeps an event out of a day it was never handed down to', function () {
    $manager = Manager::factory()->create();

    $meeting = AcademicCalendarEvent::create([
        'event_name' => 'لقاء المشرفين',
        'start_date' => '2026-09-06',
        'end_date' => '2026-09-06',
        'stage_ids' => [$this->programme->id],
        'is_attendance_period' => false,
        'created_by_id' => $manager->id,
        'created_by_type' => Manager::class,
    ]);

    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($this->cohort->id);

    // Ungoverned: nobody has started handing it down, so it behaves as every
    // event did before the chain existed.
    expect(collect(DayAgendaService::occurrences($teacher, 'teacher', '2026-09-06'))
        ->pluck('event.event_name'))->toContain('لقاء المشرفين');

    // The manager hands it to the supervisor and to nobody else. The chain now
    // governs the event, and the teacher was not in it.
    CalendarVisibility::grant($meeting, 'manager', 'supervisor', $manager);

    expect(collect(DayAgendaService::occurrences($teacher, 'teacher', '2026-09-06'))
        ->pluck('event.event_name'))->not->toContain('لقاء المشرفين');

    // And his own lesson is untouched by somebody else's meeting.
    expect(collect(DayAgendaService::occurrences($teacher, 'teacher', '2026-09-06'))
        ->pluck('event.event_name'))->toContain('درس التفسير');
});
