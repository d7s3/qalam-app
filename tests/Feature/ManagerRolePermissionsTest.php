<?php

use App\Models\Manager;
use App\Models\Role;
use App\Models\RoleScreenPermission;
use App\Models\Screen;
use Livewire\Livewire;

it('renders the permissions page for the manager with teacher pages visible', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.role-permissions'))
        ->assertSuccessful()
        ->assertSee('صلاحيات الصفحات')
        ->assertSee('إدارة الطلاب');
});

it('lets the manager disable a currently-enabled page for a role', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $teacherRole = Role::where('key', 'teacher')->firstOrFail();
    $screen = Screen::where('route_name', 'teacher.students')->firstOrFail();

    expect(RoleScreenPermission::where('role_id', $teacherRole->id)->where('screen_id', $screen->id)->exists())->toBeTrue();

    Livewire::test('manager.role-permissions')
        ->call('toggle', $teacherRole->id, $screen->id);

    expect(RoleScreenPermission::where('role_id', $teacherRole->id)->where('screen_id', $screen->id)->exists())->toBeFalse();
});

it('lets the manager re-enable a previously disabled page', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $teacherRole = Role::where('key', 'teacher')->firstOrFail();
    $screen = Screen::where('route_name', 'teacher.students')->firstOrFail();

    RoleScreenPermission::where('role_id', $teacherRole->id)->where('screen_id', $screen->id)->delete();

    Livewire::test('manager.role-permissions')
        ->call('toggle', $teacherRole->id, $screen->id);

    expect(RoleScreenPermission::where('role_id', $teacherRole->id)->where('screen_id', $screen->id)->exists())->toBeTrue();
});

it('refuses to disable a protected dashboard route', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $teacherRole = Role::where('key', 'teacher')->firstOrFail();
    $dashboardScreen = Screen::where('route_name', 'teacher.dashboard')->firstOrFail();

    Livewire::test('manager.role-permissions')
        ->call('toggle', $teacherRole->id, $dashboardScreen->id);

    expect(RoleScreenPermission::where('role_id', $teacherRole->id)->where('screen_id', $dashboardScreen->id)->exists())->toBeTrue();
});

it('switches the active role tab and shows that role pages', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $studentRole = Role::where('key', 'student')->firstOrFail();

    Livewire::test('manager.role-permissions')
        ->call('setActiveRole', $studentRole->id)
        ->assertSet('activeRoleId', $studentRole->id)
        ->assertSee('الحفظ')
        ->assertSee('المراجعة');
});

it('creates a new custom role with every screen disabled by default', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.role-permissions')
        ->set('newRoleLabel', 'مشرف مساعد')
        ->call('createRole');

    $role = Role::where('label', 'مشرف مساعد')->first();

    expect($role)->not->toBeNull();
    expect($role->is_system)->toBeFalse();
    expect($role->guard_name)->toBe('staff');
    expect(RoleScreenPermission::where('role_id', $role->id)->count())->toBe(0);
});

it('rejects creating a role with a duplicate name', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.role-permissions')
        ->set('newRoleLabel', 'مشرف مساعد')
        ->call('createRole')
        ->set('newRoleLabel', 'مشرف مساعد')
        ->call('createRole')
        ->assertHasErrors('newRoleLabel');

    expect(Role::where('label', 'مشرف مساعد')->count())->toBe(1);
});
