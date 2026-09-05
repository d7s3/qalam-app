<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\Compensation;
use App\Models\OccurrenceAttendance;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Services\CompensationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * A loss that is only reported is a loss nobody acts on. The debt stays open
 * and travels with him, apart from the current week's reckoning, until it is
 * made good.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->student = Student::factory()->create([
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
    ]);

    $this->lesson = AcademicCalendarEvent::create([
        'event_name' => 'درس التفسير',
        'start_date' => '2026-09-06',
        'end_date' => '2026-09-06',
        'stage_ids' => [$this->programme->id],
        'is_attendance_period' => false,
    ]);
});

/** Mark the student away from the lesson, however it is spelled. */
function markAway(AcademicCalendarEvent $lesson, Student $student, string $status): void
{
    OccurrenceAttendance::create([
        'academic_calendar_event_id' => $lesson->id,
        'date' => '2026-09-06',
        'user_id' => $student->id,
        'role' => 'student',
        'status' => $status,
    ]);
}

it('raises a debt for an absence somebody recorded', function () {
    markAway($this->lesson, $this->student, OccurrenceAttendance::ABSENT);

    $lane = CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');

    expect($lane)->toHaveCount(1);
    expect($lane->first()->label)->toBe('درس التفسير');
    expect($lane->first()->kind)->toBe(Compensation::FORMATIVE);
});

it('leaves an unanswered occurrence as a question, not a debt', function () {
    // Nobody said he was away. Chasing the answer is the day screen's job;
    // owing him a make-up before anyone has said he missed it is not.
    CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');

    expect(Compensation::count())->toBe(0);
});

it('owes the make-up even when the absence was excused', function () {
    markAway($this->lesson, $this->student, OccurrenceAttendance::EXCUSED);

    $lane = CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');

    expect($lane)->toHaveCount(1);
    expect($lane->first()->detail)->toContain('عذر');
});

it('never owes the same miss twice', function () {
    markAway($this->lesson, $this->student, OccurrenceAttendance::ABSENT);

    CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');
    CompensationService::raiseFor($this->student, 'student', '2026-09-01', '2026-09-30');
    CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');

    expect(Compensation::count())->toBe(1);
});

it('keeps it out of the lane once it is made good', function () {
    markAway($this->lesson, $this->student, OccurrenceAttendance::ABSENT);

    $lane = CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');

    CompensationService::complete($lane->first(), Supervisor::factory()->create(), 'حضر لقاءً تعويضياً');

    expect(CompensationService::openFor($this->student))->toHaveCount(0);
});

it('does not reopen a debt already settled', function () {
    markAway($this->lesson, $this->student, OccurrenceAttendance::ABSENT);

    $lane = CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');
    CompensationService::complete($lane->first(), Supervisor::factory()->create());

    // A later sweep of the same window must not undo somebody's decision.
    CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');

    expect(CompensationService::openFor($this->student))->toHaveCount(0);
    expect(Compensation::count())->toBe(1);
});

it('carries the debt forward rather than rescheduling it onto a week', function () {
    markAway($this->lesson, $this->student, OccurrenceAttendance::ABSENT);

    CompensationService::raiseFor($this->student, 'student', '2026-09-06', '2026-09-06');

    // Three weeks later it is still his, and its age is readable.
    Carbon::setTestNow('2026-09-27');

    $debt = CompensationService::openFor($this->student)->first();

    expect($debt)->not->toBeNull();
    expect($debt->weeksCarried())->toBe(3);

    Carbon::setTestNow();
});
