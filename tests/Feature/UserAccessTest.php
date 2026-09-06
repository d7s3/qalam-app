<?php

use App\Models\Manager;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Stage;
use App\Models\Teacher;
use App\Models\UserScreenOverride;
use App\Support\Access;
use App\Support\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Who may see which page is settled in one place, and in a fixed order: the
 * super administrator first, then an exception written for the person, then his
 * role. Three systems used to answer this separately and none knew of the
 * others, so a page that failed to appear had three possible reasons and no
 * screen that showed all three.
 */
beforeEach(function () {
    $this->admin = Manager::factory()->create(['is_super_admin' => true]);
    $this->teacher = Teacher::factory()->create();

    $this->tasmeeh = Screen::where('route_name', 'teacher.tasmeeh')->firstOrFail();
    $this->forms = Screen::where('route_name', 'supervisor.forms')->firstOrFail();
});

it('lets a role decide when nothing is written for the person', function () {
    expect(Access::canSee($this->teacher, 'teacher', 'teacher.tasmeeh'))->toBeTrue();
});

it('hides a page from one person without touching his role', function () {
    UserScreenOverride::create([
        'user_id' => $this->teacher->id,
        'screen_id' => $this->tasmeeh->id,
        'is_allowed' => false,
    ]);

    expect(Access::canSee($this->teacher, 'teacher', 'teacher.tasmeeh'))->toBeFalse();

    // Every other teacher is untouched.
    expect(Access::canSee(Teacher::factory()->create(), 'teacher', 'teacher.tasmeeh'))->toBeTrue();
});

it('opens a page to one person his role does not grant', function () {
    Role::where('key', 'teacher')->first()->screenPermissions()
        ->where('screen_id', $this->tasmeeh->id)->delete();

    expect(Access::canSee($this->teacher, 'teacher', 'teacher.tasmeeh'))->toBeFalse();

    UserScreenOverride::create([
        'user_id' => $this->teacher->id,
        'screen_id' => $this->tasmeeh->id,
        'is_allowed' => true,
    ]);

    expect(Access::canSee($this->teacher->fresh(), 'teacher', 'teacher.tasmeeh'))->toBeTrue();
});

it('shows a super administrator everything, role or no role', function () {
    Role::where('key', 'manager')->first()->screenPermissions()->delete();

    expect(Access::canSee($this->admin, 'manager', 'manager.settings'))->toBeTrue()
        ->and(Access::canSee($this->admin, 'manager', 'supervisor.forms'))->toBeTrue();
});

it('never hides a protected page, even by exception', function () {
    $dashboard = Screen::where('route_name', 'teacher.dashboard')->firstOrFail();

    UserScreenOverride::create([
        'user_id' => $this->teacher->id,
        'screen_id' => $dashboard->id,
        'is_allowed' => false,
    ]);

    expect(Access::canSee($this->teacher, 'teacher', 'teacher.dashboard'))->toBeTrue();
});

it('turns a hidden page away at the door, not only in the sidebar', function () {
    UserScreenOverride::create([
        'user_id' => $this->teacher->id,
        'screen_id' => $this->tasmeeh->id,
        'is_allowed' => false,
    ]);

    $this->actingAs($this->teacher, 'teacher')->get(route('teacher.tasmeeh'))->assertForbidden();
});

describe('the screen that hands out access', function () {
    it('is closed to a manager without the higher permission', function () {
        $plain = Manager::factory()->create(['is_super_admin' => false]);

        $this->actingAs($plain, 'manager')
            ->get(route('manager.user-access'))
            ->assertOk()
            ->assertSee('هذه الشاشة للصلاحية العليا');
    });

    it('cycles a page through inherit, open, hidden and back', function () {
        $component = Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $this->teacher->id)
            ->set('roleKey', 'teacher');

        // The role grants it, so the first step is the exception that hides it.
        $component->call('cycle', $this->tasmeeh->id);
        expect(UserScreenOverride::where('user_id', $this->teacher->id)->value('is_allowed'))->toBeFalse();

        $component->call('cycle', $this->tasmeeh->id);
        expect(UserScreenOverride::where('user_id', $this->teacher->id)->count())->toBe(0);
    });

    it('refuses to act for a manager without the higher permission', function () {
        $plain = Manager::factory()->create(['is_super_admin' => false]);

        Livewire::actingAs($plain, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $this->teacher->id)
            ->call('cycle', $this->tasmeeh->id)
            ->assertStatus(403);
    });

    it('returns a person to his role in one move', function () {
        UserScreenOverride::create([
            'user_id' => $this->teacher->id, 'screen_id' => $this->tasmeeh->id, 'is_allowed' => false,
        ]);

        Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $this->teacher->id)
            ->call('clearOverrides');

        expect(UserScreenOverride::where('user_id', $this->teacher->id)->count())->toBe(0);
    });

    it('will not let the last super administrator be removed', function () {
        $other = Manager::factory()->create(['is_super_admin' => true]);

        // Two exist, so one may go.
        Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $other->id)
            ->call('toggleSuperAdmin');
        expect($other->fresh()->is_super_admin)->toBeFalse();

        // One left, and it is himself: refused twice over.
        Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $this->admin->id)
            ->call('toggleSuperAdmin');
        expect($this->admin->fresh()->is_super_admin)->toBeTrue();
    });
});

describe('setting how far a role reaches', function () {
    it('makes a programme director from the screen, with no code', function () {
        $stage = Stage::factory()->create();
        $director = Manager::factory()->create();

        Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $director->id)
            ->set('roleKey', 'manager')
            ->set('scopeType', 'stages')
            ->set('scopeIds', [$stage->id])
            ->call('saveScope')
            ->assertHasNoErrors();

        $holding = $director->fresh('roles')->roles->firstWhere('role', 'manager');

        expect($holding->scope_type)->toBe('stages')
            ->and($holding->scope_ids)->toBe([$stage->id])
            ->and(Scope::for($director->fresh('roles'), 'manager')->reachesAll())->toBeFalse();
    });

    it('refuses a narrowed reach that names nothing', function () {
        Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $this->teacher->id)
            ->set('roleKey', 'teacher')
            ->set('scopeType', 'circles')
            ->set('scopeIds', [])
            ->call('saveScope');

        expect($this->teacher->fresh('roles')->roles->first()->scope_type)->toBeNull();
    });

    it('gives a role back to its own reach', function () {
        $this->teacher->roles()->where('role', 'teacher')
            ->update(['scope_type' => 'all', 'scope_ids' => null]);

        Livewire::actingAs($this->admin, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $this->teacher->id)
            ->set('roleKey', 'teacher')
            ->set('scopeType', '')
            ->call('saveScope');

        expect($this->teacher->fresh('roles')->roles->first()->scope_type)->toBeNull();
    });

    it('refuses a manager without the higher permission', function () {
        $plain = Manager::factory()->create(['is_super_admin' => false]);

        Livewire::actingAs($plain, 'manager')
            ->test('manager.user-access')
            ->call('selectPerson', $this->teacher->id)
            ->set('roleKey', 'teacher')
            ->set('scopeType', 'all')
            ->call('saveScope')
            ->assertStatus(403);
    });
});
