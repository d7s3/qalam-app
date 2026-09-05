<?php

use App\Livewire\Supervisor\Circles;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a new circle from the supervisor circles page', function () {
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($stage->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Circles::class)
        ->call('create')
        ->set('name', 'دفعة جديدة')
        ->set('stage_id', $stage->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Circle::where('name', 'دفعة جديدة')->exists())->toBeTrue();
});

it('updates an existing circle within the supervisor scope', function () {
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'اسم قديم', 'stage_id' => $stage->id]);
    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($stage->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Circles::class)
        ->call('edit', $circle->id)
        ->set('name', 'اسم جديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($circle->fresh()->name)->toBe('اسم جديد');
});

it('does not let a supervisor edit a circle outside their supervised stages', function () {
    $ownStage = Stage::create(['name' => 'مرحلتي']);
    $otherStage = Stage::create(['name' => 'مرحلة أخرى']);
    $otherCircle = Circle::create(['name' => 'دفعة خارجية', 'stage_id' => $otherStage->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($ownStage->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Circles::class)->call('edit', $otherCircle->id);
})->throws(ModelNotFoundException::class);

it('lets a supervisor move a circle to a different one of their own stages', function () {
    $stageA = Stage::create(['name' => 'المرحلة الأولى']);
    $stageB = Stage::create(['name' => 'المرحلة الثانية']);
    $circle = Circle::create(['name' => 'دفعة', 'stage_id' => $stageA->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach([$stageA->id, $stageB->id]);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Circles::class)
        ->call('edit', $circle->id)
        ->set('stage_id', $stageB->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($circle->fresh()->stage_id)->toBe($stageB->id);
});

it('rejects moving a circle to a stage outside the supervisor scope', function () {
    $ownStage = Stage::create(['name' => 'مرحلتي']);
    $otherStage = Stage::create(['name' => 'مرحلة أخرى']);
    $circle = Circle::create(['name' => 'دفعة', 'stage_id' => $ownStage->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($ownStage->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Circles::class)
        ->call('edit', $circle->id)
        ->set('stage_id', $otherStage->id)
        ->call('save')
        ->assertForbidden();

    expect($circle->fresh()->stage_id)->toBe($ownStage->id);
});

it('sorts circles by stage name, then circle name', function () {
    $stageB = Stage::create(['name' => 'ب - المرحلة الثانية']);
    $stageA = Stage::create(['name' => 'أ - المرحلة الأولى']);
    Circle::create(['name' => 'دفعة 2', 'stage_id' => $stageB->id]);
    Circle::create(['name' => 'دفعة 1', 'stage_id' => $stageA->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach([$stageA->id, $stageB->id]);

    $this->actingAs($supervisor, 'supervisor');

    $circles = Livewire::test(Circles::class)->get('circles');

    expect($circles->pluck('stage_id')->all())->toBe([$stageA->id, $stageB->id]);
});

it('shows the circle roster with status and progress stats when the student count is clicked', function () {
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'دفعة ابن كثير', 'stage_id' => $stage->id]);
    $student = Student::factory()->create([
        'circle_id' => $circle->id,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($stage->id);

    $this->actingAs($supervisor, 'supervisor');

    $component = Livewire::test(Circles::class)->call('viewStudents', $circle->id);

    expect($component->get('viewingCircleName'))->toBe('دفعة ابن كثير');
    $students = $component->get('viewingCircleStudents');
    expect($students)->toHaveCount(1);
    expect($students->first()['id'])->toBe($student->id);
    expect($students->first()['status'])->toBe('active');
});

it('does not let a supervisor view the roster of a circle outside their scope', function () {
    $ownStage = Stage::create(['name' => 'مرحلتي']);
    $otherStage = Stage::create(['name' => 'مرحلة أخرى']);
    $otherCircle = Circle::create(['name' => 'دفعة خارجية', 'stage_id' => $otherStage->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($ownStage->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Circles::class)->call('viewStudents', $otherCircle->id);
})->throws(ModelNotFoundException::class);
