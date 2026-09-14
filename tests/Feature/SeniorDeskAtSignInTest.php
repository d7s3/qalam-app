<?php

use App\Models\Manager;
use App\Models\UserRole;
use App\Support\RoleHierarchy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Which desk a person opens at when he holds several.
 *
 * The offices share one table and differ by a role row, so one person is
 * genuinely a manager and a supervisor and a teacher at once. The sign-in used
 * to hand him whichever guard came first in a list ordered for speed — student,
 * teacher, guardian, supervisor, manager — so the centre's manager was landed
 * at a teacher's desk every morning, saw a teacher's statistics, and had to
 * switch roles to reach his own.
 */
function personHolding(array $roles, string $email = 'many@roles.test'): Manager
{
    $person = Manager::factory()->create(['email' => $email, 'password' => bcrypt('secret123')]);

    // The factory writes the manager row of its own accord, and the offices are
    // read from these rows — so a person meant to hold only two of them has to
    // have the third taken off him, or every case here is a manager.
    UserRole::where('user_id', $person->id)->delete();

    foreach ($roles as $role) {
        UserRole::updateOrCreate(
            ['user_id' => $person->id, 'role' => $role],
            ['is_approved' => true, 'is_data_completed' => true, 'is_rejected' => false],
        );
    }

    return $person;
}

function landsOn(string $email = 'many@roles.test'): string
{
    return Livewire::test('auth.login')
        ->set('email', $email)
        ->set('password', 'secret123')
        ->call('login')
        ->effects['redirect'] ?? 'none';
}

it('opens at the manager\'s desk, not the teacher\'s', function () {
    personHolding(['manager', 'supervisor', 'teacher']);

    expect(landsOn())->toContain('/manager/dashboard');
});

it('opens at the supervisor\'s desk when there is no manager in him', function () {
    personHolding(['supervisor', 'teacher']);

    expect(landsOn())->toContain('/supervisor/dashboard');
});

it('leaves a person of one office exactly where he was', function () {
    personHolding(['teacher']);

    expect(landsOn())->toContain('/teacher/dashboard');
});

/**
 * The hierarchy is the academy's to arrange, and the desk follows it.
 *
 * An academy that says a supervisor carries the manager — however unlikely —
 * must be answered by its own arrangement rather than by an order written here.
 */
it('follows the hierarchy the academy arranged, not a list in the code', function () {
    RoleHierarchy::set(['supervisor' => ['manager'], 'manager' => []]);

    personHolding(['manager', 'supervisor']);

    expect(landsOn())->toContain('/supervisor/dashboard');

    RoleHierarchy::forget();
});
