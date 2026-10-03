<?php

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\UserRole;
use App\Support\Scope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A programme's manager acts on his own programme, and only on it.
 *
 * His lists were narrowed to what he reaches, but the buttons beside them
 * found their record by bare id: an id from the next programme — typed into
 * the browser, or left from a link — was approved, edited or deleted as
 * readily as his own. The centre's manager is unchanged.
 */
beforeEach(function () {
    Scope::forget();

    $this->mine = Stage::factory()->create(['name' => 'برنامجي']);
    $this->theirs = Stage::factory()->create(['name' => 'برنامجهم']);
    $this->theirCohort = Circle::factory()->create(['stage_id' => $this->theirs->id]);
    Circle::factory()->create(['stage_id' => $this->mine->id]);

    $this->theirTeacher = Teacher::factory()->create(['is_approved' => true]);
    $this->theirTeacher->circles()->attach($this->theirCohort->id);
    $this->theirSupervisor = Supervisor::factory()->create(['is_approved' => true]);
    $this->theirSupervisor->stages()->attach($this->theirs->id);
    $this->theirGuardian = Guardian::factory()->create(['is_approved' => true]);
    $this->theirStudent = Student::factory()->create([
        'circle_id' => $this->theirCohort->id,
        'stage_id' => $this->theirs->id,
        'guardian_id' => $this->theirGuardian->id,
        'is_approved' => true,
    ]);
    $this->emptyCohort = Circle::factory()->create(['stage_id' => $this->theirs->id]);

    $this->director = Manager::factory()->create();
    $this->director->roles()->where('role', 'manager')->update([
        'scope_type' => UserRole::SCOPE_STAGES,
        'scope_ids' => [$this->mine->id],
    ]);
    $this->director->load('roles');

    $this->centre = Manager::factory()->create();
});

dataset('another programme\'s records', [
    'معلم' => ['manager.teachers', 'theirTeacher'],
    'مشرف' => ['manager.supervisors', 'theirSupervisor'],
    'طالب' => ['manager.students', 'theirStudent'],
    'ولي أمر' => ['manager.guardians', 'theirGuardian'],
    'دفعة' => ['manager.circles', 'emptyCohort'],
]);

it('does not delete a record of another programme', function (string $screen, string $record) {
    $target = $this->{$record};

    try {
        Livewire::actingAs($this->director, 'manager')->test($screen)->call('delete', $target->id);
    } catch (ModelNotFoundException) {
        // Refused by not finding it — as good as a refusal in words.
    }

    expect($target::query()->whereKey($target->id)->exists())->toBeTrue();
})->with('another programme\'s records');

it('still lets the centre\'s manager delete it', function (string $screen, string $record) {
    $target = $this->{$record};

    Livewire::actingAs($this->centre, 'manager')->test($screen)->call('delete', $target->id);

    expect($target::query()->whereKey($target->id)->exists())->toBeFalse();
})->with('another programme\'s records');
