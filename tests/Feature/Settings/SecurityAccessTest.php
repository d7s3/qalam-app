<?php

use App\Livewire\Settings\Security;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The security/password settings page must be reachable by every guard, not
 * just the default `web` guard. Previously the Fortify `password.confirm`
 * gate redirected non-web guards to a web-only screen and locked them out.
 *
 * @return array{0: class-string, 1: string}
 */
dataset('guards', [
    'manager' => [Manager::class, 'manager'],
    'supervisor' => [Supervisor::class, 'supervisor'],
    'teacher' => [Teacher::class, 'teacher'],
    'student' => [Student::class, 'student'],
    'guardian' => [Guardian::class, 'guardian'],
]);

it('can reach the security settings page', function (string $model, string $guard) {
    $user = $model::factory()->create();

    $this->actingAs($user, $guard)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSeeLivewire(Security::class);
})->with('guards');

it('can change the password from the security page', function (string $model, string $guard) {
    $user = $model::factory()->create();

    $this->actingAs($user, $guard);

    Livewire::test(Security::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
})->with('guards');

it('rejects a wrong current password', function (string $model, string $guard) {
    $user = $model::factory()->create();

    $this->actingAs($user, $guard);

    Livewire::test(Security::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with('guards');
