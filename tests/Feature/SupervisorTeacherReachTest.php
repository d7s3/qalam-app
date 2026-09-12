<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Support\Scope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The supervisor's and teacher's areas, swept for the same fault.
 *
 * Their screens were written for offices that were always narrow, so most of
 * them confine by hand — reading the supervisor's own programmes rather than
 * asking `Scope`. That is duplication rather than a leak, and the sweep below
 * had to be read before it was believed.
 *
 * One was a leak: the form builder offered every programme in the academy as an
 * audience. Publishing was never in danger — `clampToAuthor` cuts the audience
 * down to the author's own reach — so what escaped was the names, and a tick
 * that was then dropped without a word.
 */
beforeEach(function () {
    Scope::forget();

    $this->mine = Stage::factory()->create(['name' => 'برنامجي']);
    $this->theirs = Stage::factory()->create(['name' => 'برنامجهم']);

    $this->myCohort = Circle::factory()->create(['stage_id' => $this->mine->id, 'name' => 'دفعتي']);
    $this->theirCohort = Circle::factory()->create(['stage_id' => $this->theirs->id, 'name' => 'دفعتهم']);

    $this->supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $this->supervisor->stages()->attach($this->mine->id);

    $this->teacher = Teacher::factory()->create(['is_approved' => true]);
    $this->teacher->circles()->attach($this->myCohort->id);

    Student::factory()->create([
        'name' => 'طالبي', 'circle_id' => $this->myCohort->id, 'stage_id' => $this->mine->id, 'is_approved' => true,
    ]);

    Student::factory()->create([
        'name' => 'طالبهم', 'circle_id' => $this->theirCohort->id, 'stage_id' => $this->theirs->id, 'is_approved' => true,
    ]);
});

it('offers a form\'s author only the programmes he holds', function () {
    $rendered = Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.form-builder')
        ->html();

    expect($rendered)->toContain('برنامجي')
        ->and($rendered)->not->toContain('برنامجهم');
});

it('shows a supervisor his own programme\'s people and not the next one\'s', function (string $screen) {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test($screen)
        ->assertSee('طالبي')
        ->assertDontSee('طالبهم');
})->with([
    'الطلاب' => 'supervisor.students',
]);

it('refuses a supervisor a cohort report outside his programmes', function () {
    // The screens that take an id from the address are the ones worth checking:
    // a listing that hides a row still hands it over if the row can be asked
    // for by name.
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.circle-report', ['circleId' => $this->myCohort->id])
        ->assertSuccessful();

    expect(fn () => Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.circle-report', ['circleId' => $this->theirCohort->id]))
        ->toThrow(ModelNotFoundException::class);
});

it('refuses a teacher a recitation log for a boy outside his cohort', function () {
    $mine = Student::where('name', 'طالبي')->first();
    $theirs = Student::where('name', 'طالبهم')->first();

    $screen = Livewire::actingAs($this->teacher, 'teacher')->test('teacher.student-recitation-log');

    expect($screen->instance()->mayEdit($mine))->toBeTrue()
        ->and($screen->instance()->mayEdit($theirs))->toBeFalse();
})->skip(fn () => ! method_exists(
    'App\Livewire\Teacher\StudentRecitationLog', 'mayEdit',
), 'الدالة خاصّة — يغطّيها StudentRecitationLogEditTest');
