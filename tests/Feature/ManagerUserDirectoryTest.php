<?php

use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Supervisor;
use App\Models\Teacher;
use Livewire\Livewire;

it('defaults to the students tab on the students route', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.students'))
        ->assertSuccessful()
        ->assertSee('الطلاب')
        ->assertSee('المعلمون');
});

it('defaults to the teachers tab on the teachers route', function () {
    $manager = Manager::factory()->create();
    $teacher = Teacher::factory()->create(['is_approved' => true]);
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.teachers'))
        ->assertSuccessful()
        ->assertSee($teacher->name);
});

it('defaults to the supervisors tab on the supervisors route', function () {
    $manager = Manager::factory()->create();
    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.supervisors'))
        ->assertSuccessful()
        ->assertSee($supervisor->name);
});

it('defaults to the guardians tab on the guardians route', function () {
    $manager = Manager::factory()->create();
    $guardian = Guardian::factory()->create(['is_approved' => true]);
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.guardians'))
        ->assertSuccessful()
        ->assertSee($guardian->name);
});

it('switches tabs within the same page and shows the other role data', function () {
    $manager = Manager::factory()->create();
    $teacher = Teacher::factory()->create(['is_approved' => true]);
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.user-directory', ['initialTab' => 'students'])
        ->assertSet('activeTab', 'students')
        ->call('setTab', 'teachers')
        ->assertSet('activeTab', 'teachers')
        ->assertSee($teacher->name);
});

it('ignores an unknown initial tab and falls back to students', function () {
    Livewire::test('manager.user-directory', ['initialTab' => 'not-a-real-tab'])
        ->assertSet('activeTab', 'students');
});
