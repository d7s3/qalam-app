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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A manager over one programme sees that programme, and nothing beside it.
 *
 * The tier was built and the reach was written, and every screen the manager's
 * area holds went on asking for everybody — because all of them were written
 * when «manager» meant the man over the centre and there was nothing to narrow.
 * `Scope` knew he was narrowed; not one screen asked it.
 *
 * The result was the quiet kind of wrong: a programme's director opened his
 * teachers and was shown the academy's, opened his cohorts and was shown every
 * cohort, and nothing on any page said he was looking at somebody else's work.
 *
 * The centre's own manager is unchanged throughout, and each test says so —
 * a narrowing that also narrowed him would be a different bug wearing this
 * one's clothes.
 */
beforeEach(function () {
    Scope::forget();

    $this->mine = Stage::factory()->create(['name' => 'برنامجي']);
    $this->theirs = Stage::factory()->create(['name' => 'برنامجهم']);

    $this->myCohort = Circle::factory()->create(['stage_id' => $this->mine->id, 'name' => 'دفعتي']);
    $this->theirCohort = Circle::factory()->create(['stage_id' => $this->theirs->id, 'name' => 'دفعتهم']);

    $this->myTeacher = Teacher::factory()->create(['name' => 'معلمي', 'is_approved' => true]);
    $this->myTeacher->circles()->attach($this->myCohort->id);

    $this->theirTeacher = Teacher::factory()->create(['name' => 'معلمهم', 'is_approved' => true]);
    $this->theirTeacher->circles()->attach($this->theirCohort->id);

    $this->mySupervisor = Supervisor::factory()->create(['name' => 'مشرفي', 'is_approved' => true]);
    $this->mySupervisor->stages()->attach($this->mine->id);

    $this->theirSupervisor = Supervisor::factory()->create(['name' => 'مشرفهم', 'is_approved' => true]);
    $this->theirSupervisor->stages()->attach($this->theirs->id);

    $this->myGuardian = Guardian::factory()->create(['name' => 'وليّي', 'is_approved' => true]);
    $this->theirGuardian = Guardian::factory()->create(['name' => 'وليّهم', 'is_approved' => true]);

    Student::factory()->create([
        'name' => 'طالبي', 'circle_id' => $this->myCohort->id,
        'stage_id' => $this->mine->id, 'guardian_id' => $this->myGuardian->id, 'is_approved' => true,
    ]);

    Student::factory()->create([
        'name' => 'طالبهم', 'circle_id' => $this->theirCohort->id,
        'stage_id' => $this->theirs->id, 'guardian_id' => $this->theirGuardian->id, 'is_approved' => true,
    ]);

    // The man over one programme, and the man over the centre.
    $this->director = Manager::factory()->create(['name' => 'مدير برنامجي']);
    $this->director->roles()->where('role', 'manager')->update([
        'scope_type' => UserRole::SCOPE_STAGES,
        'scope_ids' => [$this->mine->id],
    ]);
    $this->director->load('roles');

    $this->centre = Manager::factory()->create(['name' => 'مدير المركز']);
});

/** Every screen, asked the same question, from both offices. */
dataset('the manager\'s listings', [
    'المعلمون' => ['manager.teachers', 'معلمي', 'معلمهم'],
    'المشرفون' => ['manager.supervisors', 'مشرفي', 'مشرفهم'],
    'الطلاب' => ['manager.students', 'طالبي', 'طالبهم'],
    'الأوصياء' => ['manager.guardians', 'وليّي', 'وليّهم'],
    'الدفعات' => ['manager.circles', 'دفعتي', 'دفعتهم'],
]);

it('shows a programme\'s manager his own and not the next programme\'s', function (string $screen, string $mine, string $theirs) {
    Livewire::actingAs($this->director, 'manager')
        ->test($screen)
        ->assertSee($mine)
        ->assertDontSee($theirs);
})->with('the manager\'s listings');

it('still shows the centre\'s manager both', function (string $screen, string $mine, string $theirs) {
    Livewire::actingAs($this->centre, 'manager')
        ->test($screen)
        ->assertSee($mine)
        ->assertSee($theirs);
})->with('the manager\'s listings');

it('offers the calendar only the programmes he holds', function () {
    $his = Livewire::actingAs($this->director, 'manager')
        ->test('manager.academic-calendar')
        ->instance()
        ->availableStages()
        ->pluck('name');

    expect($his->all())->toBe(['برنامجي']);

    // The reach is remembered for the length of a request, and a request holds
    // one reader — so two readers in one test have to be told apart by hand.
    Scope::forget();

    $all = Livewire::actingAs($this->centre, 'manager')
        ->test('manager.academic-calendar')
        ->instance()
        ->availableStages()
        ->pluck('name');

    expect($all->all())->toEqualCanonicalizing(['برنامجي', 'برنامجهم']);
});

it('adds the four reaches that were missing from Scope', function () {
    $his = Scope::for($this->director, 'manager');

    expect($his->applyToTeachers(Teacher::query())->pluck('name')->all())->toBe(['معلمي'])
        ->and($his->applyToSupervisors(Supervisor::query())->pluck('name')->all())->toBe(['مشرفي'])
        ->and($his->applyToStages(Stage::query())->pluck('name')->all())->toBe(['برنامجي'])
        ->and($his->applyToGuardians(Guardian::query())->pluck('name')->all())->toBe(['وليّي']);

    Scope::forget();
    $centre = Scope::for($this->centre, 'manager');

    // The centre's reach is untouched: a narrowing that narrowed him too would
    // be a different fault wearing this one's clothes.
    expect($centre->applyToTeachers(Teacher::query())->count())->toBe(2)
        ->and($centre->applyToSupervisors(Supervisor::query())->count())->toBe(2)
        ->and($centre->applyToStages(Stage::query())->count())->toBe(2)
        ->and($centre->applyToGuardians(Guardian::query())->count())->toBe(2);
});
