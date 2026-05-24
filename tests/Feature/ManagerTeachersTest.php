<?php

use App\Livewire\Manager\Teachers as ManagerTeachers;
use App\Livewire\Supervisor\Teachers as SupervisorTeachers;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\Teacher;
use Livewire\Livewire;

it('allows manager to view and edit teacher phone number', function () {
    $manager = Manager::factory()->create();
    $teacher = Teacher::factory()->create([
        'phone' => '0512345678',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(ManagerTeachers::class)
        ->call('edit', $teacher->id)
        ->assertSet('editingTeacherId', $teacher->id)
        ->assertSet('phone', '0512345678')
        ->set('phone', '0599999999')
        ->call('save')
        ->assertHasNoErrors();

    expect($teacher->refresh()->phone)->toBe('0599999999');
});

it('allows supervisor to view and edit teacher phone number', function () {
    $supervisor = Supervisor::factory()->create();
    $stage = Stage::factory()->create();
    $supervisor->stages()->attach($stage->id);

    $circle = Circle::factory()->create([
        'stage_id' => $stage->id,
    ]);

    $teacher = Teacher::factory()->create([
        'phone' => '0512345678',
    ]);
    $teacher->circles()->attach($circle->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(SupervisorTeachers::class)
        ->call('edit', $teacher->id)
        ->assertSet('editingTeacherId', $teacher->id)
        ->assertSet('phone', '0512345678')
        ->set('phone', '0588888888')
        ->call('save')
        ->assertHasNoErrors();

    expect($teacher->refresh()->phone)->toBe('0588888888');
});
