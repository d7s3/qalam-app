<?php

use App\Models\Manager;
use App\Models\Stage;
use App\Notifications\AccountInvitation;
use App\Support\ManagerTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * An account made for somebody has a password nobody knows — a long random
 * string, written so no person ever handles another person's password. That
 * leaves the man with no way in unless the account writes to him itself.
 */
beforeEach(function () {
    $this->centre = Manager::factory()->create(['name' => 'مدير المركز']);
    $this->programme = Stage::factory()->create();
});

it('writes to a manager the moment he is made', function () {
    Notification::fake();

    Livewire::actingAs($this->centre, 'manager')
        ->test('manager.managers')
        ->set('name', 'مدير جديد')
        ->set('email', 'invited@example.com')
        ->set('tier', ManagerTier::PROGRAMME)
        ->set('reaches', [$this->programme->id])
        ->call('create')
        ->assertHasNoErrors();

    $made = Manager::where('email', 'invited@example.com')->firstOrFail();

    Notification::assertSentTo($made, AccountInvitation::class);
});

it('sends a link and never a password', function () {
    $invited = Manager::factory()->create(['name' => 'المدعوّ']);

    $mail = (new AccountInvitation('مدير المركز'))->toMail($invited);
    $rendered = $mail->render();

    expect($rendered)->toContain('اضبط كلمة المرور')
        ->and($rendered)->toContain('/invitation/'.$invited->id)
        // Nothing that could be mistaken for a password travels with it.
        ->and($rendered)->not->toContain($invited->password);
});

it('opens the door with a signed link and shuts it to an unsigned one', function () {
    $invited = Manager::factory()->create(['is_approved' => false]);

    $this->get(AccountInvitation::linkFor($invited))
        ->assertSuccessful()
        ->assertSee($invited->email);

    // The same address without the signature is refused.
    $this->get(route('invitation.accept', ['user' => $invited->id]))
        ->assertForbidden();
});

it('refuses a link that has run out', function () {
    $invited = Manager::factory()->create();
    $link = AccountInvitation::linkFor($invited);

    $this->travel(AccountInvitation::DAYS + 1)->days();

    $this->get($link)->assertForbidden();
});

it('lets him set his own password and opens the account', function () {
    $invited = Manager::factory()->create([
        'is_approved' => false,
        'password' => Hash::make('a-random-string-nobody-knows'),
    ]);

    Livewire::test('auth.accept-invitation', ['user' => $invited])
        ->set('password', 'قلم123')
        ->set('password_confirmation', 'قلم123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('done', true);

    $invited->refresh();

    expect(Hash::check('قلم123', $invited->password))->toBeTrue()
        // The invitation was the approval; he is not left at a locked door.
        ->and($invited->is_approved)->toBeTrue();
});

it('holds him to the same six characters everybody else keeps', function () {
    $invited = Manager::factory()->create();

    Livewire::test('auth.accept-invitation', ['user' => $invited])
        ->set('password', 'قلم1')
        ->set('password_confirmation', 'قلم1')
        ->call('save')
        ->assertHasErrors('password')
        ->assertSet('done', false);
});

it('keeps the account when the letter cannot be posted', function () {
    // A mail server that is down must not take the account with it.
    Notification::fake();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP is unreachable'));

    Livewire::actingAs($this->centre, 'manager')
        ->test('manager.managers')
        ->set('name', 'رغم العطل')
        ->set('email', 'despite@example.com')
        ->set('tier', ManagerTier::CENTRE)
        ->call('create')
        ->assertHasNoErrors();

    expect(Manager::where('email', 'despite@example.com')->exists())->toBeTrue();
});
