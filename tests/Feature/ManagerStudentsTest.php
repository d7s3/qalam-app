<?php

use App\Livewire\Manager\Students;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Student;
use Livewire\Livewire;

it('allows manager to edit and save a student using Students livewire component', function () {
    $manager = Manager::factory()->create();
    $circle = Circle::factory()->create();
    $student = Student::factory()->create([
        'circle_id' => $circle->id,
        'status' => 'active',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(Students::class)
        ->call('edit', $student->id)
        ->assertSet('editingStudentId', $student->id)
        ->set('name', 'Updated Student Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->refresh()->name)->toBe('Updated Student Name');
});

it('changes the student status through the dedicated status manager', function () {
    $manager = Manager::factory()->create();
    $circle = Circle::factory()->create();
    $student = Student::factory()->create([
        'circle_id' => $circle->id,
        'status' => 'active',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test('shared.⚡student-status-manager')
        ->call('open', $student->id)
        ->set('newStatus', 'suspended')
        ->set('reason', 'انقطاع متكرر عن الحضور')
        ->call('saveStatus')
        ->assertHasNoErrors();

    expect($student->refresh()->status)->toBe('suspended');
});
