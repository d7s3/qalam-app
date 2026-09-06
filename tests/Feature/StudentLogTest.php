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
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A note is the author's, and reaches anyone else only because he said so.
 * Seniority does not open it: a supervisor who reads everything by his office
 * reads a diary rather than a record, and the teacher stops writing honestly.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->student = Student::factory()->create([
        'name' => 'سالم',
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
    ]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programme->id);

    $this->manager = Manager::factory()->create();
});

it('keeps a note to the one who wrote it', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'كان منقبضاً هذا الأسبوع.');

    expect(StudentLogService::mayRead($note, $this->teacher, 'teacher'))->toBeTrue();

    // Neither his supervisor nor the manager, by their office alone.
    expect(StudentLogService::mayRead($note, $this->supervisor, 'supervisor'))->toBeFalse();
    expect(StudentLogService::mayRead($note, $this->manager, 'manager'))->toBeFalse();
});

it('opens one note to one office when its author says so', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة');

    StudentLogService::openTo($note, $this->teacher, 'supervisor');

    expect(StudentLogService::mayRead($note->fresh(), $this->supervisor, 'supervisor'))->toBeTrue();

    // And the manager is still outside it — this was one office, not everyone above.
    expect(StudentLogService::mayRead($note->fresh(), $this->manager, 'manager'))->toBeFalse();
});

it('lets the author take an opening back', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة');

    StudentLogService::openTo($note, $this->teacher, 'supervisor');
    StudentLogService::closeTo($note, $this->teacher, 'supervisor');

    expect(StudentLogService::mayRead($note->fresh(), $this->supervisor, 'supervisor'))->toBeFalse();
});

it('opens a note to everyone who may see the student when asked', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة', null, StudentNote::SHARED);

    expect(StudentLogService::mayRead($note, $this->supervisor, 'supervisor'))->toBeTrue();
    expect(StudentLogService::mayRead($note, $this->manager, 'manager'))->toBeTrue();
});

it('refuses to let anybody but the author open a note', function () {
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة');

    expect(StudentLogService::openTo($note, $this->supervisor, 'manager'))->toBeFalse();
    expect(StudentLogService::setVisibility($note, $this->manager, StudentNote::SHARED))->toBeFalse();

    expect($note->fresh()->isShared())->toBeFalse();
    expect($note->fresh()->shares()->count())->toBe(0);
});

it('leaves the super administrator above it', function () {
    $admin = Manager::factory()->create(['is_super_admin' => true]);
    $note = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة');

    expect(StudentLogService::mayRead($note, $admin, 'manager'))->toBeTrue();
});

it('shows each reader his own and what was opened to him', function () {
    StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة المعلّم');
    $opened = StudentLogService::write($this->student, $this->teacher, 'teacher', 'ملاحظة مفتوحة');
    StudentLogService::openTo($opened, $this->teacher, 'supervisor');
    StudentLogService::write($this->student, $this->supervisor, 'supervisor', 'ملاحظة المشرف');

    expect(StudentLogService::visibleTo($this->student, $this->teacher, 'teacher')->pluck('body')->all())
        ->toEqualCanonicalizing(['ملاحظة المعلّم', 'ملاحظة مفتوحة']);

    expect(StudentLogService::visibleTo($this->student, $this->supervisor, 'supervisor')->pluck('body')->all())
        ->toEqualCanonicalizing(['ملاحظة مفتوحة', 'ملاحظة المشرف']);
});

it('writes and opens from the screen', function () {
    $component = Livewire::actingAs($this->teacher, 'teacher')
        ->test('shared.student-log')
        ->set('asRole', 'teacher')
        ->call('open', $this->student->id)
        ->set('body', 'وقف مع أخٍ أصغر منه اليوم.')
        ->set('notedOn', '2026-09-06')
        ->call('save')
        ->assertHasNoErrors();

    $note = StudentNote::firstOrFail();

    expect($note->author_id)->toBe($this->teacher->id);
    expect($note->visibility)->toBe('private');

    $component->call('toggleShare', $note->id, 'supervisor');

    expect(StudentLogService::mayRead($note->fresh(), $this->supervisor, 'supervisor'))->toBeTrue();
});

it('refuses to write about a student outside his reach', function () {
    $stranger = Student::factory()->create([
        'circle_id' => Circle::factory()->create(['stage_id' => Stage::factory()->create()->id])->id,
    ]);

    Livewire::actingAs($this->teacher, 'teacher')
        ->test('shared.student-log')
        ->set('asRole', 'teacher')
        ->call('open', $stranger->id)
        ->set('body', 'ليس طالبه')
        ->call('save')
        ->assertStatus(404);

    expect(StudentNote::count())->toBe(0);
});
