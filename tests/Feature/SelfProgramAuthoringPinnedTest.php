<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Writing the programme was the supervisor's, reached from above through a
 * group that is collapsed by default — which is why nobody found it. It is
 * pinned in all three sidebars now.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create(['name' => 'برنامج الحفظ']);
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->manager = Manager::factory()->create();

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programme->id);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);
});

it('opens for each of the three offices', function () {
    $this->actingAs($this->manager, 'manager')
        ->get(route('manager.self-program-weeks'))->assertSuccessful()->assertSee('كتابة البرنامج الذاتي');

    $this->actingAs($this->teacher, 'teacher')
        ->get(route('teacher.self-program-weeks'))->assertSuccessful();

    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.self-program-weeks'))->assertSuccessful();
});

it('is a link in each of the three sidebars', function () {
    $this->actingAs($this->manager, 'manager')
        ->get(route('manager.dashboard'))
        ->assertSee(route('manager.self-program-weeks'), false);

    $this->actingAs($this->teacher, 'teacher')
        ->get(route('teacher.dashboard'))
        ->assertSee(route('teacher.self-program-weeks'), false);
});

it('tells a teacher that the programme reaches past his cohort', function () {
    // The self programme is written for a programme; the enrichment is what is
    // written for a cohort. Handing him the screen without saying so would let
    // him overwrite the work of circles he does not teach without knowing it.
    $this->actingAs($this->teacher, 'teacher')
        ->get(route('teacher.self-program-weeks'))
        ->assertSee('أنت تكتب لبرنامج كامل')
        ->assertSee('ومنها دفعات غيرك');

    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.self-program-weeks'))
        ->assertDontSee('أنت تكتب لبرنامج كامل');
});

it('lets each of them write a week for the programme he reaches', function () {
    foreach ([[$this->manager, 'manager'], [$this->supervisor, 'supervisor'], [$this->teacher, 'teacher']] as $index => [$user, $role]) {
        Livewire::actingAs($user, $role)
            ->test('supervisor.self-program-weeks')
            ->set('asRole', $role)
            ->set('stageId', $this->programme->id)
            ->set('newStartsOn', '2026-10-'.str_pad((string) (4 + $index * 7), 2, '0', STR_PAD_LEFT))
            ->call('addWeek')
            ->assertHasNoErrors();
    }

    expect(SelfProgramWeek::self()->where('stage_id', $this->programme->id)->count())->toBe(3);
});

it('refuses a programme the writer does not reach', function () {
    $elsewhere = Stage::factory()->create();

    Livewire::actingAs($this->teacher, 'teacher')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'teacher')
        ->set('stageId', $elsewhere->id)
        ->set('newStartsOn', '2026-10-04')
        ->call('addWeek')
        ->assertStatus(403);

    expect(SelfProgramWeek::count())->toBe(0);
});
