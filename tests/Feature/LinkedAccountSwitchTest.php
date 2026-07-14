<?php

use App\Models\Manager;
use App\Models\Teacher;
use App\Models\UserRole;

it('does not show the role-switch section when the user has only one role', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('التبديل بين أدوارك');
});

it('shows the other role as a switch button in the header dropdown', function () {
    $manager = Manager::factory()->create();
    UserRole::create(['user_id' => $manager->id, 'role' => 'teacher', 'is_approved' => true]);

    $this->actingAs($manager, 'manager');

    $this->get(route('manager.dashboard'))
        ->assertSuccessful()
        ->assertSee('التبديل بين أدوارك')
        ->assertSee('معلم حلقة');
});

it('switches into another role for the same account without a password and keeps both guards authenticated', function () {
    $manager = Manager::factory()->create();
    UserRole::create(['user_id' => $manager->id, 'role' => 'teacher', 'is_approved' => true]);

    $this->actingAs($manager, 'manager');

    $this->post(route('switch-role', ['guard' => 'teacher']))
        ->assertRedirect(route('teacher.dashboard'));

    $this->assertAuthenticatedAs($manager, 'manager');
    expect(auth('teacher')->id())->toBe($manager->id);
});

it('rejects switching into a role the account does not hold', function () {
    $manager = Manager::factory()->create();
    // Deliberately no teacher role granted.

    $this->actingAs($manager, 'manager');

    $this->post(route('switch-role', ['guard' => 'teacher']))
        ->assertForbidden();

    $this->assertAuthenticatedAs($manager, 'manager');
    expect(auth('teacher')->check())->toBeFalse();
});

it('rejects switching into an unknown guard name', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $this->post(route('switch-role', ['guard' => 'not-a-real-guard']))
        ->assertNotFound();
});

it('shows the correct active role after switching back and forth, even once every guard is authenticated', function () {
    $teacher = Teacher::factory()->create(['is_approved' => true]);
    UserRole::create(['user_id' => $teacher->id, 'role' => 'supervisor', 'is_approved' => true]);

    $this->actingAs($teacher, 'teacher');

    // Switching teacher -> supervisor logs both guards in simultaneously.
    $this->post(route('switch-role', ['guard' => 'supervisor']))
        ->assertRedirect(route('supervisor.dashboard'));

    // The small role label under the user's name in the desktop header is
    // the unambiguous "currently active role" indicator (distinct from the
    // "switch to X" buttons, which show every *other* role's label too).
    $this->get(route('supervisor.dashboard'))
        ->assertSuccessful()
        ->assertSee('<div class="text-xs text-zinc-400">مشرف</div>', false);

    // Switching back must not get stuck showing "supervisor" just because
    // that guard happens to come first in a fixed priority list — the
    // active role should follow the current route, not a fixed scan order.
    $this->post(route('switch-role', ['guard' => 'teacher']))
        ->assertRedirect(route('teacher.dashboard'));

    $this->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertSee('<div class="text-xs text-zinc-400">معلم حلقة</div>', false);
});
