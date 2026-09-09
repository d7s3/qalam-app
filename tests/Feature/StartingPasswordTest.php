<?php

use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Support\ManagerTier;
use App\Support\StartingPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Most people here cannot register themselves — a manager, a supervisor, a
 * cohort's teacher are made for them by the office above. Such an account is
 * opened on one code the academy hands out, said over the phone rather than
 * written down anywhere.
 *
 * One code for every new account is only safe while it cannot survive the first
 * sign-in, and that is what these hold to.
 */
it('hands out the academy\'s code, which is 123456 until it says otherwise', function () {
    expect(StartingPassword::code())->toBe('123456')
        ->and(StartingPassword::isDefault())->toBeTrue();

    StartingPassword::set('قلم1448');

    expect(StartingPassword::code())->toBe('قلم1448')
        ->and(StartingPassword::isDefault())->toBeFalse();
});

it('opens a new account on the code, and marks it as owing a change', function () {
    $centre = Manager::factory()->create();
    $programme = Stage::factory()->create();

    Livewire::actingAs($centre, 'manager')
        ->test('manager.managers')
        ->set('name', 'مدير جديد')
        ->set('email', 'fresh@example.com')
        ->set('tier', ManagerTier::PROGRAMME)
        ->set('reaches', [$programme->id])
        ->call('create')
        ->assertHasNoErrors();

    $made = Manager::where('email', 'fresh@example.com')->firstOrFail();

    expect(Hash::check(StartingPassword::code(), $made->password))->toBeTrue()
        ->and($made->must_change_password)->toBeTrue();
});

it('shows him one screen and nothing else until he has chosen his own', function () {
    $owing = Manager::factory()->create(['must_change_password' => true]);

    $this->actingAs($owing, 'manager')
        ->get(route('manager.dashboard'))
        ->assertRedirect(route('password.starting'));

    // The screen he is sent to is not itself sent away.
    $this->actingAs($owing, 'manager')
        ->get(route('password.starting'))
        ->assertSuccessful();
});

it('lets everybody else through untouched', function () {
    $settled = Manager::factory()->create(['must_change_password' => false]);

    $this->actingAs($settled, 'manager')
        ->get(route('manager.dashboard'))
        ->assertSuccessful();
});

it('takes the code off him when he chooses his own', function () {
    $owing = Manager::factory()->create([
        'must_change_password' => true,
        'password' => Hash::make(StartingPassword::code()),
    ]);

    Livewire::actingAs($owing, 'manager')
        ->test('auth.starting-password')
        ->set('password', 'قلمي123')
        ->set('password_confirmation', 'قلمي123')
        ->call('save')
        ->assertHasNoErrors();

    $owing->refresh();

    expect($owing->must_change_password)->toBeFalse()
        ->and(Hash::check('قلمي123', $owing->password))->toBeTrue()
        ->and(Hash::check(StartingPassword::code(), $owing->password))->toBeFalse();
});

it('refuses the very code he arrived on', function () {
    $owing = Manager::factory()->create([
        'must_change_password' => true,
        'password' => Hash::make(StartingPassword::code()),
    ]);

    Livewire::actingAs($owing, 'manager')
        ->test('auth.starting-password')
        ->set('password', StartingPassword::code())
        ->set('password_confirmation', StartingPassword::code())
        ->call('save')
        ->assertHasErrors('password');

    expect($owing->fresh()->must_change_password)->toBeTrue();
});

it('holds a student on the code to the same screen', function () {
    // The gate is on every area, not the manager's alone.
    $student = Student::factory()->create(['must_change_password' => true, 'is_approved' => true]);

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard'))
        ->assertRedirect(route('password.starting'));
});
