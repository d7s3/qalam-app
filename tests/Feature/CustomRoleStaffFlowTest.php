<?php

use App\Livewire\Auth\Login;
use App\Models\Manager;
use App\Models\Role;
use App\Models\RoleScreenPermission;
use App\Models\Screen;
use App\Models\Staff;
use Livewire\Livewire;

it('lets a manager create a custom role, grant it a screen, create a staff account, and see the granted screen previewed in the staff sidebar', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    // 1. Create the custom role from the permissions screen.
    Livewire::test('manager.role-permissions')
        ->set('newRoleLabel', 'محاسب')
        ->call('createRole');

    $role = Role::where('label', 'محاسب')->firstOrFail();
    expect($role->guard_name)->toBe('staff');
    expect(RoleScreenPermission::where('role_id', $role->id)->count())->toBe(0);

    // 2. Grant it one existing screen from another role's namespace.
    $screen = Screen::where('route_name', 'manager.attendance-reports')->firstOrFail();

    Livewire::test('manager.role-permissions')
        ->call('setActiveRole', $role->id)
        ->call('toggle', $role->id, $screen->id);

    expect(RoleScreenPermission::where('role_id', $role->id)->where('screen_id', $screen->id)->exists())->toBeTrue();

    // 3. Create a staff account under that role from the staff-members screen.
    Livewire::test('manager.staff-members')
        ->set('name', 'محاسب المجمع')
        ->set('email', 'accountant@altag-test.com')
        ->set('password', 'Password123!')
        ->set('selectedRoleId', $role->id)
        ->call('create');

    $staff = Staff::where('email', 'accountant@altag-test.com')->firstOrFail();
    expect($staff->staff_role_id)->toBe($role->id);
    expect($staff->is_approved)->toBeTrue();

    // 4. Staff logs in via the unified login and lands on their own dashboard.
    auth()->logout();

    Livewire::test(Login::class)
        ->set('email', 'accountant@altag-test.com')
        ->set('password', 'Password123!')
        ->call('login')
        ->assertRedirect(route('staff.dashboard'));

    $this->assertAuthenticatedAs($staff, 'staff');

    // 5. The granted (but not-yet-wired) screen shows up as a preview, and
    // messaging works since it's guard-agnostic.
    $this->get(route('staff.dashboard'))
        ->assertSuccessful()
        ->assertSee('تقارير الحضور والغياب')
        ->assertSee('قريباً');

    $this->get(route('staff.messages'))->assertSuccessful();
});
