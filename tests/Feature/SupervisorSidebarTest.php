<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\UserRole;
use App\Services\StudentPlacementService;
use App\Support\Scope;

/**
 * The supervisor's sidebar: short, and nothing lost.
 *
 * It ran to some thirty-five entries, every one at the same weight, so the
 * four a sheikh opens every day were lost among the ones he opens once a
 * term. The everyday ones now stand at the top, the rest fold into groups
 * that open on their own when he is on one of their pages — and every page
 * it linked to is still a click away.
 */
beforeEach(function () {
    $programme = Stage::factory()->create();
    $this->group = Circle::factory()->create(['stage_id' => $programme->id]);

    $this->sheikh = Supervisor::factory()->create();
    $this->sheikh->stages()->attach($programme->id);
    $this->sheikh->roles()->where('role', 'supervisor')->update(['scope_type' => UserRole::SCOPE_CIRCLES, 'scope_ids' => [$this->group->id]]);
    $this->sheikh->load('roles');

    Scope::forget();
    $this->actingAs($this->sheikh, 'supervisor');
});

it('still links every page it linked to', function (string $route) {
    $this->get(route('supervisor.dashboard'))
        ->assertSuccessful()
        ->assertSee(route($route), false);
})->with([
    'supervisor.academic-calendar', 'supervisor.circles', 'supervisor.competitions', 'supervisor.dashboard',
    'supervisor.event-visibility', 'supervisor.exceeded-limits', 'supervisor.forms', 'supervisor.hadiths',
    'supervisor.hadiths.paths', 'supervisor.messages', 'supervisor.motivations', 'supervisor.my-day',
    'supervisor.odes', 'supervisor.odes.paths', 'supervisor.odes.plans', 'supervisor.period-values',
    'supervisor.placement-requests', 'supervisor.portal', 'supervisor.reports', 'supervisor.self-program-progress',
    'supervisor.self-program-record', 'supervisor.self-program-tracks', 'supervisor.self-program-weeks',
    'supervisor.student-log', 'supervisor.students', 'supervisor.task-automation', 'supervisor.task-board',
    'supervisor.tasks', 'supervisor.teacher-competitions', 'supervisor.teachers', 'supervisor.whatsapp-settings',
    'supervisor.yearly-attendance',
]);

it('puts the everyday pages first, and folds the rest away', function () {
    $html = $this->get(route('supervisor.dashboard'))->getContent();

    $first = fn (string $route) => strpos($html, 'href="'.route($route).'"');

    expect($first('supervisor.students'))->toBeLessThan($first('supervisor.odes'))
        ->and($first('supervisor.reports'))->toBeLessThan($first('supervisor.task-board'))
        ->and($html)->toContain('data-flux-sidebar-group');
});

it('counts on «الطلاب» the students waiting for his group', function () {
    StudentPlacementService::askToJoin(Student::factory()->create(['circle_id' => null]), $this->group);
    StudentPlacementService::askToJoin(Student::factory()->create(['circle_id' => null]), $this->group);

    $this->get(route('supervisor.dashboard'))
        ->assertSeeInOrder(['الطلاب', '2']);
});
