<?php

use App\Livewire\Supervisor\Students;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * The student a supervisor saves is the one he opened, and it is one of his.
 */
beforeEach(function () {
    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);

    $this->otherCircle = Circle::factory()->create();

    $this->student = Student::factory()->create(['circle_id' => $this->circle->id, 'status' => 'active']);
    $this->outsider = Student::factory()->create(['circle_id' => $this->otherCircle->id, 'status' => 'active']);

    $this->actingAs($this->supervisor, 'supervisor');
});

it('saves the student that was opened', function () {
    Livewire::test(Students::class)
        ->call('edit', $this->student->id)
        ->set('name', 'اسم جديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->student->fresh()->name)->toBe('اسم جديد');
});

it('refuses to swap the opened student for one outside his reach', function () {
    Livewire::test(Students::class)
        ->call('edit', $this->student->id)
        ->set('editingStudentId', $this->outsider->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('does not open a student outside his reach', function () {
    Livewire::test(Students::class)
        ->call('edit', $this->outsider->id)
        ->assertSet('editingStudentId', null);
});

it('links only a guardian as the student\'s guardian', function () {
    $manager = Manager::factory()->create();

    Livewire::test(Students::class)
        ->call('edit', $this->student->id)
        ->set('guardian_id', $manager->id)
        ->call('save')
        ->assertHasErrors('guardian_id');

    $guardian = Guardian::factory()->create();

    Livewire::test(Students::class)
        ->call('edit', $this->student->id)
        ->set('guardian_id', $guardian->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->student->fresh()->guardian_id)->toBe($guardian->id);
});

it('does nothing when saved with no student open', function () {
    Livewire::test(Students::class)
        ->set('name', 'اسم')
        ->set('email', 'x@example.com')
        ->call('save')
        ->assertHasNoErrors();
});
