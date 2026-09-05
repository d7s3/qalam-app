<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The two screens either side of a placement: the teacher asking, and the
 * supervisor answering.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create(['name' => 'برنامج الحفظ']);
    $this->other = Stage::factory()->create(['name' => 'برنامج آخر']);

    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id, 'name' => 'دفعة الفجر']);
    $this->otherCohort = Circle::factory()->create(['stage_id' => $this->other->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programme->id);

    $this->mine = Student::factory()->create([
        'name' => 'سالم', 'circle_id' => null, 'stage_id' => $this->programme->id,
    ]);

    $this->theirs = Student::factory()->create([
        'name' => 'زياد', 'circle_id' => null, 'stage_id' => $this->other->id,
    ]);
});

it('turns the teacher click into a request, not a placement', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.student-manager')
        ->call('addToCircle', $this->mine->id);

    expect($this->mine->fresh()->circle_id)->toBeNull();
    expect(StudentPlacementRequest::pending()->where('student_id', $this->mine->id)->count())->toBe(1);
});

it('refuses a student who belongs to another programme', function () {
    // The pool used to be every unplaced student in the academy, so this was a
    // click away rather than an error.
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.student-manager')
        ->assertDontSee('زياد');

    expect(fn () => Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.student-manager')
        ->call('addToCircle', $this->theirs->id))
        ->toThrow(ModelNotFoundException::class);

    expect(StudentPlacementRequest::count())->toBe(0);
});

it('creates a new student holding nothing until the supervisor answers', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.student-manager')
        ->set('name', 'عبدالله الجديد')
        ->set('phone', '0500000000')
        ->call('createStudent');

    $student = Student::where('name', 'عبدالله الجديد')->firstOrFail();

    // He used to be created approved and inside the cohort outright, which let
    // this button walk around the whole approval chain.
    expect($student->circle_id)->toBeNull();
    expect($student->stage_id)->toBeNull();
    expect($student->is_approved)->toBeFalsy();

    expect(StudentPlacementRequest::pending()->where('student_id', $student->id)->count())->toBe(1);
});

it('lets the supervisor place him from his queue', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.student-manager')
        ->call('addToCircle', $this->mine->id);

    $request = StudentPlacementRequest::pending()->firstOrFail();

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.placement-requests')
        ->assertSee('سالم')
        ->call('approve', $request->id);

    expect($this->mine->fresh()->circle_id)->toBe($this->cohort->id);
});

it('keeps another supervisor out of that queue', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.student-manager')
        ->call('addToCircle', $this->mine->id);

    $request = StudentPlacementRequest::pending()->firstOrFail();

    $stranger = Supervisor::factory()->create();
    $stranger->stages()->attach($this->other->id);

    Livewire::actingAs($stranger, 'supervisor')
        ->test('supervisor.placement-requests')
        ->assertDontSee('سالم');

    expect(fn () => Livewire::actingAs($stranger, 'supervisor')
        ->test('supervisor.placement-requests')
        ->call('approve', $request->id))
        ->toThrow(ModelNotFoundException::class);

    expect($this->mine->fresh()->circle_id)->toBeNull();
});

it('admits a student into a programme from the queue', function () {
    $waiting = Student::factory()->create(['name' => 'خالد', 'circle_id' => null, 'stage_id' => null]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.placement-requests')
        ->set('admitStage', $this->programme->id)
        ->call('admit', $waiting->id);

    expect($waiting->fresh()->stage_id)->toBe($this->programme->id);
});

it('refuses to admit into a programme the supervisor does not hold', function () {
    $waiting = Student::factory()->create(['circle_id' => null, 'stage_id' => null]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.placement-requests')
        ->set('admitStage', $this->other->id)
        ->call('admit', $waiting->id)
        ->assertStatus(403);

    expect($waiting->fresh()->stage_id)->toBeNull();
});
