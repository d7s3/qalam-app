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
        ->assertSet('editStatus', 'active')
        ->set('name', 'Updated Student Name')
        ->set('editStatus', 'suspended')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->refresh()->name)->toBe('Updated Student Name');
    expect($student->refresh()->status)->toBe('suspended');
});
