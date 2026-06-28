<?php

use App\Livewire\Manager\Stages;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = Manager::factory()->create();
});

it('opens the edit modal for a stage with no description', function () {
    $stage = Stage::factory()->create(['description' => null]);

    $this->actingAs($this->manager, 'manager');

    Livewire::test(Stages::class)
        ->call('edit', $stage->id)
        ->assertSet('description', '')
        ->assertSet('name', $stage->name);
});

it('opens the edit modal after the only assigned supervisor has been deleted', function () {
    $supervisor = Supervisor::factory()->create();
    $stage = Stage::factory()->create(['description' => null]);
    $stage->supervisors()->attach($supervisor);
    $supervisor->delete();

    $this->actingAs($this->manager, 'manager');

    Livewire::test(Stages::class)
        ->call('edit', $stage->id)
        ->assertSet('selectedSupervisors', []);
});
