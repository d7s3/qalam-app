<?php

use App\Models\Manager;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\UserScreenOverride;
use App\Support\Access;
use App\Support\RoleHierarchy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Seniority includes.
 *
 * What a cohort teacher may open, his supervisor may open, and the centre
 * manager above them both — settled once in the chain rather than by granting
 * the same screen three times and watching the three drift apart.
 */
beforeEach(function () {
    RoleHierarchy::forget();
    Access::forget();

    $this->admin = Manager::factory()->create(['is_super_admin' => true]);
    $this->supervisor = Supervisor::factory()->create();
    $this->teacher = Teacher::factory()->create();

    $this->tasmeeh = Screen::where('route_name', 'teacher.tasmeeh')->firstOrFail();
});

it('carries a teacher\'s screens up to his supervisor and the manager', function () {
    expect(Access::canSee($this->supervisor, 'supervisor', 'teacher.tasmeeh'))->toBeTrue()
        ->and(Access::canSee($this->admin, 'manager', 'teacher.tasmeeh'))->toBeTrue();
});

it('does not carry a supervisor\'s screens down to his teachers', function () {
    // Seniority includes; juniority does not.
    expect(Access::canSee($this->teacher, 'teacher', 'supervisor.circles'))->toBeFalse();
});

it('reaches through one office to the one below it', function () {
    // The manager carries the supervisor, and through him the teacher, without
    // anyone writing that down twice.
    expect(RoleHierarchy::inheritedBy('manager'))->toEqualCanonicalizing(['supervisor', 'teacher']);
});

it('stops carrying a screen the moment the role below loses it', function () {
    Role::where('key', 'teacher')->first()->screenPermissions()
        ->where('screen_id', $this->tasmeeh->id)->delete();
    Access::forget();

    expect(Access::canSee($this->supervisor, 'supervisor', 'teacher.tasmeeh'))->toBeFalse();
});

it('lets an exception for one person overrule what his office carries', function () {
    UserScreenOverride::create([
        'user_id' => $this->supervisor->id,
        'screen_id' => $this->tasmeeh->id,
        'is_allowed' => false,
    ]);

    expect(Access::canSee($this->supervisor, 'supervisor', 'teacher.tasmeeh'))->toBeFalse()
        // And no other supervisor is touched.
        ->and(Access::canSee(Supervisor::factory()->create(), 'supervisor', 'teacher.tasmeeh'))->toBeTrue();
});

describe('arranging it from the screen', function () {
    it('changes who carries whom without a release', function () {
        Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->set('inherits', ['manager' => ['teacher']])
            ->call('saveHierarchy');

        RoleHierarchy::forget();
        Access::forget();

        expect(RoleHierarchy::inheritedBy('manager'))->toBe(['teacher'])
            // The supervisor no longer carries the teacher, since the chain said so.
            ->and(Access::canSee($this->supervisor, 'supervisor', 'teacher.tasmeeh'))->toBeFalse()
            ->and(Access::canSee($this->admin, 'manager', 'teacher.tasmeeh'))->toBeTrue();
    });

    it('refuses a role that would carry itself', function () {
        Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->set('inherits', ['supervisor' => ['teacher'], 'teacher' => ['supervisor']])
            ->call('saveHierarchy');

        RoleHierarchy::forget();

        // A cycle makes "who carries whom" unanswerable, so it is not written.
        expect(RoleHierarchy::inheritedBy('supervisor'))->not->toContain('supervisor');
    });

    it('is closed to a manager without the higher permission', function () {
        Livewire::actingAs(Manager::factory()->create(['is_super_admin' => false]), 'manager')
            ->test('manager.user-access')
            ->set('inherits', ['manager' => []])
            ->call('saveHierarchy')
            ->assertStatus(403);
    });
});

it('leaves the roles themselves meaning what they meant', function () {
    // Widening the guard so a supervisor loaded as a teacher was tried and
    // undone: `Teacher::all()` means the teaching staff, and every list that
    // says "the teachers" broke when it stopped meaning that.
    expect(Teacher::pluck('id'))->toContain($this->teacher->id)
        ->and(Teacher::pluck('id'))->not->toContain($this->supervisor->id);
});
