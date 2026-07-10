<?php

use App\Livewire\Manager\Circles;
use App\Livewire\Manager\Guardians;
use App\Livewire\Manager\Stages;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a new circle from the manager circles page', function () {
    $manager = Manager::factory()->create();
    $stage = Stage::create(['name' => 'المرحلة الأولى']);

    $this->actingAs($manager, 'manager');

    Livewire::test(Circles::class)
        ->call('create')
        ->set('name', 'حلقة جديدة')
        ->set('stage_id', $stage->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Circle::where('name', 'حلقة جديدة')->exists())->toBeTrue();
});

it('updates an existing circle', function () {
    $manager = Manager::factory()->create();
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'اسم قديم', 'stage_id' => $stage->id]);

    $this->actingAs($manager, 'manager');

    Livewire::test(Circles::class)
        ->call('edit', $circle->id)
        ->set('name', 'اسم جديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($circle->fresh()->name)->toBe('اسم جديد');
});

it('creates a new stage from the manager stages page', function () {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(Stages::class)
        ->call('create')
        ->set('name', 'مرحلة جديدة')
        ->call('save')
        ->assertHasNoErrors();

    expect(Stage::where('name', 'مرحلة جديدة')->exists())->toBeTrue();
});

it('updates an existing stage', function () {
    $manager = Manager::factory()->create();
    $stage = Stage::create(['name' => 'اسم قديم']);

    $this->actingAs($manager, 'manager');

    Livewire::test(Stages::class)
        ->call('edit', $stage->id)
        ->set('name', 'اسم جديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($stage->fresh()->name)->toBe('اسم جديد');
});

it('updates an existing guardian', function () {
    $manager = Manager::factory()->create();
    $guardian = Guardian::factory()->create(['name' => 'اسم قديم', 'is_approved' => true]);

    $this->actingAs($manager, 'manager');

    Livewire::test(Guardians::class)
        ->call('edit', $guardian->id)
        ->set('name', 'اسم جديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($guardian->fresh()->name)->toBe('اسم جديد');
});
