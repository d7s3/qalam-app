<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Services\StudentPlacementService;
use App\Support\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A student belongs to a cohort because someone decided it and someone senior
 * agreed — not because a teacher clicked a name out of a pool holding every
 * unplaced student in the academy.
 */
beforeEach(function () {
    $this->programmeA = Stage::factory()->create(['name' => 'برنامج الحفظ']);
    $this->programmeB = Stage::factory()->create(['name' => 'برنامج المبتدئين']);

    $this->cohortA = Circle::factory()->create(['stage_id' => $this->programmeA->id, 'name' => 'دفعة الفجر']);
    $this->cohortB = Circle::factory()->create(['stage_id' => $this->programmeB->id, 'name' => 'دفعة الضحى']);

    $this->teacher = Teacher::factory()->create(['name' => 'أستاذ أحمد']);
    $this->teacher->circles()->attach($this->cohortA->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programmeA->id);

    // Admitted to a programme, waiting for a cohort.
    $this->waiting = Student::factory()->create([
        'name' => 'سالم', 'circle_id' => null, 'stage_id' => $this->programmeA->id,
    ]);

    // Admitted to the other programme.
    $this->otherProgramme = Student::factory()->create([
        'name' => 'زياد', 'circle_id' => null, 'stage_id' => $this->programmeB->id,
    ]);

    // Registered, not admitted anywhere yet.
    $this->unadmitted = Student::factory()->create([
        'name' => 'خالد', 'circle_id' => null, 'stage_id' => null,
    ]);
});

/** The pool the teacher actually chooses from. */
function poolFor($teacher): array
{
    return Scope::for($teacher, 'teacher')
        ->applyToUnplacedStudents(Student::query())
        ->pluck('name')
        ->all();
}

it('bounds the pool to the teacher own programme', function () {
    // It used to be Student::whereNull('circle_id') with no scope at all: every
    // unplaced student in the academy, whichever programme he registered for.
    expect(poolFor($this->teacher))->toBe(['سالم']);
});

it('keeps a student nobody admitted out of every pool', function () {
    expect(poolFor($this->teacher))->not->toContain('خالد');

    // Admitting him is what makes him visible, and it is the supervisor's to do.
    StudentPlacementService::admitToProgramme($this->unadmitted, $this->programmeA, $this->supervisor);

    expect(poolFor($this->teacher))->toContain('خالد');
});

it('does not place a student when the teacher asks', function () {
    StudentPlacementService::request($this->waiting, $this->cohortA, $this->teacher);

    expect($this->waiting->fresh()->circle_id)->toBeNull();
    expect(StudentPlacementRequest::pending()->count())->toBe(1);
});

it('places him when the supervisor agrees', function () {
    $request = StudentPlacementService::request($this->waiting, $this->cohortA, $this->teacher);

    StudentPlacementService::approve($request, $this->supervisor);

    $student = $this->waiting->fresh();

    expect($student->circle_id)->toBe($this->cohortA->id);
    expect($student->stage_id)->toBe($this->programmeA->id);
    expect($student->status)->toBe('active');

    $request->refresh();
    expect($request->status)->toBe(StudentPlacementRequest::APPROVED);
    expect($request->decided_by)->toBe($this->supervisor->id);
    expect($request->decided_at)->not->toBeNull();
});

it('answers the other teachers who asked for the same student', function () {
    $second = Teacher::factory()->create();
    $otherCohort = Circle::factory()->create(['stage_id' => $this->programmeA->id]);
    $second->circles()->attach($otherCohort->id);

    $mine = StudentPlacementService::request($this->waiting, $this->cohortA, $this->teacher);
    $theirs = StudentPlacementService::request($this->waiting, $otherCohort, $second);

    StudentPlacementService::approve($mine, $this->supervisor);

    // Left pending, their queue would never have moved.
    expect($theirs->fresh()->status)->toBe(StudentPlacementRequest::REJECTED);
});

it('treats asking twice as the same ask', function () {
    $first = StudentPlacementService::request($this->waiting, $this->cohortA, $this->teacher);
    $again = StudentPlacementService::request($this->waiting, $this->cohortA, $this->teacher);

    expect($again->id)->toBe($first->id);
    expect(StudentPlacementRequest::count())->toBe(1);
});

it('keeps the reason when the supervisor declines', function () {
    $request = StudentPlacementService::request($this->waiting, $this->cohortA, $this->teacher);

    StudentPlacementService::reject($request, $this->supervisor, 'الدفعة مكتملة');

    $request->refresh();

    expect($request->status)->toBe(StudentPlacementRequest::REJECTED);
    expect($request->note)->toBe('الدفعة مكتملة');
    expect($this->waiting->fresh()->circle_id)->toBeNull();
});

it('leaves a removed student himself, only not attending', function () {
    StudentPlacementService::approve(
        StudentPlacementService::request($this->waiting, $this->cohortA, $this->teacher),
        $this->supervisor,
    );

    StudentPlacementService::deactivate($this->waiting->fresh(), 'انقطع عن الحضور');

    $student = $this->waiting->fresh();

    expect($student->status)->toBe('inactive');
    expect($student->circle_id)->toBeNull();

    // His name, his record and his programme are exactly where they were.
    expect($student->name)->toBe('سالم');
    expect($student->stage_id)->toBe($this->programmeA->id);
    expect($student->statusHistories()->count())->toBeGreaterThan(1);

    // And he is his programme's to place again, not the academy's.
    expect(poolFor($this->teacher))->toContain('سالم');
});
