<?php

use App\Livewire\Supervisor\Teachers as SupervisorTeachers;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'مرحلة المعلمين']);
    $this->circle = Circle::create(['name' => 'حلقة المعلمين', 'stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);

    $this->actingAs($this->supervisor, 'supervisor');
});

it('lets a supervisor quick-create a teacher without an approved_by FK violation', function () {
    Livewire::test(SupervisorTeachers::class)
        ->set('quickName', 'بدر العطاس')
        ->set('quickPhone', '0501112222')
        ->set('quickCircleId', $this->circle->id)
        ->call('createQuickTeacher')
        ->assertHasNoErrors();

    $teacher = Teacher::where('name', 'بدر العطاس')->first();
    expect($teacher)->not->toBeNull();
    expect((bool) $teacher->is_approved)->toBeTrue();
    // approved_by references managers; a supervisor isn't a manager, so it stays null.
    expect($teacher->approved_by)->toBeNull();
    expect($teacher->circles->pluck('id'))->toContain($this->circle->id);
});

it('lets a supervisor approve a teacher in scope without an FK violation', function () {
    $teacher = Teacher::factory()->create(['is_approved' => false]);
    $teacher->circles()->attach($this->circle->id);

    Livewire::test(SupervisorTeachers::class)
        ->call('approve', $teacher->id);

    expect((bool) $teacher->fresh()->is_approved)->toBeTrue();
    expect($teacher->fresh()->approved_by)->toBeNull();
});
