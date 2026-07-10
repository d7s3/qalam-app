<?php

use App\Models\DisabledRolePage;
use App\Models\Manager;
use Livewire\Livewire;

it('renders the permissions page for the manager with teacher pages by default', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.role-permissions'))
        ->assertSuccessful()
        ->assertSee('صلاحيات الصفحات')
        ->assertSee('إدارة الطلاب');
});

it('lets the manager disable a page for a role, persisting a real row', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.role-permissions')
        ->call('toggle', 'teacher', 'teacher.students');

    $row = DisabledRolePage::where('role', 'teacher')->where('route', 'teacher.students')->first();
    expect($row)->not->toBeNull();
    expect($row->disabled_by)->toBe($manager->id);
});

it('lets the manager re-enable a previously disabled page', function () {
    $manager = Manager::factory()->create();
    DisabledRolePage::create(['role' => 'teacher', 'route' => 'teacher.students', 'disabled_by' => $manager->id]);
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.role-permissions')
        ->call('toggle', 'teacher', 'teacher.students');

    expect(DisabledRolePage::where('role', 'teacher')->where('route', 'teacher.students')->exists())->toBeFalse();
});

it('refuses to disable a protected dashboard route', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.role-permissions')
        ->set('activeRole', 'teacher')
        ->call('toggle', 'teacher', 'teacher.dashboard');

    expect(DisabledRolePage::where('route', 'teacher.dashboard')->exists())->toBeFalse();
});

it('switches the active role tab and shows that role pages', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.role-permissions')
        ->call('setActiveRole', 'student')
        ->assertSet('activeRole', 'student')
        ->assertSee('الحفظ')
        ->assertSee('المراجعة');
});
