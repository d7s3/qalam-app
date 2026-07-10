<?php

use App\Models\DisabledRolePage;
use App\Models\Manager;
use App\Models\Teacher;
use App\Support\RolePages;

it('treats every registered page as enabled by default', function () {
    expect(RolePages::isEnabled('teacher', 'teacher.students'))->toBeTrue();
    expect(RolePages::isEnabled('supervisor', 'supervisor.circles'))->toBeTrue();
});

it('disables a page once a DisabledRolePage row exists for that role and route', function () {
    DisabledRolePage::create(['role' => 'teacher', 'route' => 'teacher.students']);

    expect(RolePages::isEnabled('teacher', 'teacher.students'))->toBeFalse();
    expect(RolePages::isEnabled('teacher', 'teacher.attendance'))->toBeTrue();
});

it('also blocks nested child routes when the parent page is disabled', function () {
    DisabledRolePage::create(['role' => 'teacher', 'route' => 'teacher.leaderboards']);

    expect(RolePages::isEnabled('teacher', 'teacher.leaderboards'))->toBeFalse();
    expect(RolePages::isEnabled('teacher', 'teacher.leaderboards.grade'))->toBeFalse();
});

it('never disables the dashboard route even if a row exists for it', function () {
    DisabledRolePage::create(['role' => 'teacher', 'route' => 'teacher.dashboard']);

    expect(RolePages::isEnabled('teacher', 'teacher.dashboard'))->toBeTrue();
});

it('blocks direct URL access to a disabled page via the enforcement middleware', function () {
    $teacher = Teacher::factory()->create();
    DisabledRolePage::create(['role' => 'teacher', 'route' => 'teacher.discipline']);

    $this->actingAs($teacher, 'teacher')
        ->get(route('teacher.discipline'))
        ->assertForbidden();

    $this->get(route('teacher.attendance'))->assertSuccessful();
});

it('does not affect manager routes since manager permissions are not controlled', function () {
    $manager = Manager::factory()->create();
    DisabledRolePage::create(['role' => 'manager', 'route' => 'manager.students']);

    $this->actingAs($manager, 'manager')
        ->get(route('manager.students'))
        ->assertSuccessful();
});
