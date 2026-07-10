<?php

use App\Livewire\Supervisor\Circles;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Supervisor;
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
        ->set('name', 'حلقة جديدة')
        ->set('stage_id', $stage->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Circle::where('name', 'حلقة جديدة')->exists())->toBeTrue();
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
    $otherCircle = Circle::create(['name' => 'حلقة خارجية', 'stage_id' => $otherStage->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($ownStage->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Circles::class)->call('edit', $otherCircle->id);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
