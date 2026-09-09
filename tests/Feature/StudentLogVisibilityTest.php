<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Services\StudentLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The record of upbringing, and who may read it.
 *
 * This is the one place in the application where seniority does not open a
 * door. A teacher writes honestly about a boy only if he is the one who decides
 * who reads it; a supervisor who can read everything by virtue of his office is
 * reading a diary rather than a record.
 *
 * So the rule runs against the grain of every other rule here, which is exactly
 * why it needs holding: a later change that makes the log obey the seniority
 * chain — as everything else does — would look like a tidy-up and would quietly
 * open every private note in the academy.
 *
 * It had no test at all before this.
 */
beforeEach(function () {
    $programme = Stage::factory()->create();
    $cohort = Circle::factory()->create(['stage_id' => $programme->id]);

    $this->student = Student::factory()->create(['circle_id' => $cohort->id, 'stage_id' => $programme->id]);

    $this->teacher = Teacher::factory()->create(['name' => 'المعلم الكاتب']);
    $this->teacher->circles()->attach($cohort->id);

    $this->otherTeacher = Teacher::factory()->create(['name' => 'معلم آخر']);
    $this->otherTeacher->circles()->attach($cohort->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($programme->id);

    $this->manager = Manager::factory()->create();
    $this->admin = Manager::factory()->create(['is_super_admin' => true]);
});

it('keeps a private note to the man who wrote it', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة خاصة');

    expect(StudentLogService::mayRead($note, $this->teacher, 'teacher'))->toBeTrue()
        // Not his colleague in the same cohort.
        ->and(StudentLogService::mayRead($note, $this->otherTeacher, 'teacher'))->toBeFalse()
        // Nor the supervisor above him, nor the manager above them both:
        // seniority carries screens, and this is not one of them.
        ->and(StudentLogService::mayRead($note, $this->supervisor, 'supervisor'))->toBeFalse()
        ->and(StudentLogService::mayRead($note, $this->manager, 'manager'))->toBeFalse();
});

it('lets the administrator read it, as everywhere else', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة خاصة');

    expect(StudentLogService::mayRead($note, $this->admin, 'manager'))->toBeTrue();
});

it('opens a note to one office and to no other', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'للمشرف وحده');

    StudentLogService::openTo($note, $this->teacher, 'supervisor');
    $note->load('shares');

    expect(StudentLogService::mayRead($note, $this->supervisor, 'supervisor'))->toBeTrue()
        ->and(StudentLogService::mayRead($note, $this->manager, 'manager'))->toBeFalse()
        ->and(StudentLogService::mayRead($note, $this->otherTeacher, 'teacher'))->toBeFalse();
});

it('lets nobody but the author open a note', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة');

    // A supervisor cannot hand himself the note by sharing it with his own
    // office, which would make the rule a formality.
    expect(StudentLogService::openTo($note, $this->supervisor, 'supervisor'))->toBeFalse()
        ->and($note->fresh('shares')->shares)->toBeEmpty()
        ->and(StudentLogService::mayRead($note->fresh('shares'), $this->supervisor, 'supervisor'))->toBeFalse();
});

it('shows a note written openly to everyone who reaches the boy', function () {
    $note = StudentLogService::write(
        $this->student, $this->teacher, 'teacher', 'ملاحظة معلنة', null, StudentNote::SHARED,
    );

    expect(StudentLogService::mayRead($note, $this->supervisor, 'supervisor'))->toBeTrue()
        ->and(StudentLogService::mayRead($note, $this->otherTeacher, 'teacher'))->toBeTrue();
});

it('hands each reader his own share of the record, and no more', function () {
    $mine = StudentLogService::write($this->student, $this->teacher, 'teacher', 'خاصتي');
    $theirs = StudentLogService::write($this->student, $this->otherTeacher, 'teacher', 'خاصتهم');
    $open = StudentLogService::write(
        $this->student, $this->otherTeacher, 'teacher', 'معلنة', null, StudentNote::SHARED,
    );

    $forShare = StudentLogService::write($this->student, $this->otherTeacher, 'teacher', 'للمشرف');
    StudentLogService::openTo($forShare, $this->otherTeacher, 'supervisor');

    $teacherSees = StudentLogService::visibleTo($this->student, $this->teacher, 'teacher')->pluck('body');
    $supervisorSees = StudentLogService::visibleTo($this->student, $this->supervisor, 'supervisor')->pluck('body');
    $adminSees = StudentLogService::visibleTo($this->student, $this->admin, 'manager')->pluck('body');

    expect($teacherSees->all())->toEqualCanonicalizing(['خاصتي', 'معلنة'])
        ->and($supervisorSees->all())->toEqualCanonicalizing(['معلنة', 'للمشرف'])
        ->and($adminSees)->toHaveCount(4)
        // And nothing anybody sees was invented: every note exists.
        ->and(StudentNote::count())->toBe(4);
});
