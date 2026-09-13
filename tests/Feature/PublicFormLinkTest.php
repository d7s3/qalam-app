<?php

use App\Models\Form;
use App\Models\Manager;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Handing a form's link to people who have no account.
 *
 * The public page existed before this screen did, which meant the only way to
 * open a form to strangers was to write a row by hand — so in practice no form
 * was ever opened. This is the button that does it, and the guards around it.
 */
function formOwnedBy(Supervisor $owner, array $overrides = []): Form
{
    return Form::create(array_merge([
        'supervisor_id' => $owner->id,
        'created_by_id' => $owner->id,
        'created_by_type' => 'supervisor',
        'title' => 'استمارة التحاق',
        'slug' => 'trial-'.Str::random(6),
        'color' => '#14837B',
        'status' => 'published',
        'published_at' => now(),
        'fields' => [
            ['id' => 'name', 'type' => 'text', 'label' => 'الاسم', 'required' => true, 'options' => []],
        ],
    ], $overrides));
}

it('mints a link the first time the owner opens a form', function () {
    $owner = Supervisor::factory()->create();
    $form = formOwnedBy($owner);

    expect($form->public_token)->toBeNull()
        ->and($form->publicUrl())->toBeNull();

    Livewire::actingAs($owner, 'supervisor')
        ->test('supervisor.manage-forms')
        ->call('share', $form->id)
        ->assertSet('isPublic', false)
        ->set('isPublic', true)
        ->call('saveSharing')
        ->assertHasNoErrors();

    $form->refresh();

    expect($form->is_public)->toBeTrue()
        ->and($form->public_token)->not->toBeNull()
        ->and($form->isOpenToPublic())->toBeTrue();

    // And the link it printed is a link that actually opens.
    $this->get($form->publicUrl())->assertSuccessful()->assertSee('استمارة التحاق');
});

/**
 * Closing a form must not burn its link.
 *
 * The link goes out on paper and in messages the academy cannot recall. If
 * shutting the form for a week minted a new token on reopening, every copy
 * already in circulation would quietly stop working.
 */
it('keeps the same link when a form is closed and opened again', function () {
    $owner = Supervisor::factory()->create();
    $form = formOwnedBy($owner);

    $screen = Livewire::actingAs($owner, 'supervisor')->test('supervisor.manage-forms');

    $screen->call('share', $form->id)->set('isPublic', true)->call('saveSharing');
    $first = $form->refresh()->public_token;

    $screen->call('share', $form->id)->set('isPublic', false)->call('saveSharing');
    expect($form->refresh()->is_public)->toBeFalse()
        ->and($form->public_token)->toBe($first);

    $this->get(route('forms.apply', ['token' => $first]))->assertStatus(410);

    $screen->call('share', $form->id)->set('isPublic', true)->call('saveSharing');

    expect($form->refresh()->public_token)->toBe($first);
    $this->get(route('forms.apply', ['token' => $first]))->assertSuccessful();
});

it('carries the closing date and the introduction to the public page', function () {
    $owner = Supervisor::factory()->create();
    $form = formOwnedBy($owner);

    Livewire::actingAs($owner, 'supervisor')
        ->test('supervisor.manage-forms')
        ->call('share', $form->id)
        ->set('isPublic', true)
        ->set('closesOn', now()->addWeek()->toDateString())
        ->set('publicIntro', 'برنامجٌ نوعيٌّ لنخبة الطلاب')
        ->call('saveSharing')
        ->assertHasNoErrors();

    $this->get($form->refresh()->publicUrl())
        ->assertSuccessful()
        ->assertSee('برنامجٌ نوعيٌّ لنخبة الطلاب')
        ->assertSee('التسجيل متاح حتى');

    // And the date is the gate, not decoration.
    $form->update(['closes_on' => now()->subDay()]);
    $this->get($form->publicUrl())->assertStatus(410);
});

/**
 * Seeing a form is not owning it.
 *
 * A form marked shared appears on every supervisor's screen. Letting any one of
 * them hand it to the public would mean the person who wrote a form is not the
 * person who decides who may answer it.
 */
it('refuses to let a supervisor open somebody else\'s shared form', function () {
    $owner = Supervisor::factory()->create();
    $other = Supervisor::factory()->create();
    $form = formOwnedBy($owner, ['is_supervisor_shared' => true]);

    // He can see it — that is the shape of the leak being guarded against.
    Livewire::actingAs($other, 'supervisor')
        ->test('supervisor.manage-forms')
        ->assertSee('استمارة التحاق');

    Livewire::actingAs($other, 'supervisor')
        ->test('supervisor.manage-forms')
        ->call('share', $form->id)
        ->assertStatus(403);

    // And not by setting the id himself and saving past the panel.
    Livewire::actingAs($other, 'supervisor')
        ->test('supervisor.manage-forms')
        ->set('sharingId', $form->id)
        ->set('isPublic', true)
        ->call('saveSharing')
        ->assertStatus(403);

    expect($form->refresh()->is_public)->toBeFalse()
        ->and($form->public_token)->toBeNull();
});

it('lets a manager open any form, since the academy is his to answer for', function () {
    $owner = Supervisor::factory()->create();
    $form = formOwnedBy($owner);

    Livewire::actingAs(Manager::factory()->create(), 'manager')
        ->test('supervisor.manage-forms')
        ->call('share', $form->id)
        ->set('isPublic', true)
        ->call('saveSharing')
        ->assertHasNoErrors();

    expect($form->refresh()->isOpenToPublic())->toBeTrue();
});

/**
 * A draft opened to the public is the worst state there is.
 *
 * The switch says open, the badge says open, and the link returns 410 — so the
 * academy believes it is taking applications while every father who follows the
 * link is turned away. Opening must not publish the draft behind the owner's
 * back either; it must say so.
 */
it('says plainly when an opened link is still dead', function () {
    $owner = Supervisor::factory()->create();
    $form = formOwnedBy($owner, ['status' => 'draft', 'published_at' => null]);

    $screen = Livewire::actingAs($owner, 'supervisor')
        ->test('supervisor.manage-forms')
        ->call('share', $form->id)
        ->set('isPublic', true)
        ->call('saveSharing');

    expect($form->refresh()->status)->toBe('draft')
        ->and($form->isOpenToPublic())->toBeFalse();

    $screen->assertSee('ما زال مسودّة', false)
        ->assertSee('مفتوح ورابطه معطّل');

    $this->get($form->publicUrl())->assertStatus(410);
});

it('refuses a closing date that is not a date', function () {
    $owner = Supervisor::factory()->create();
    $form = formOwnedBy($owner);

    Livewire::actingAs($owner, 'supervisor')
        ->test('supervisor.manage-forms')
        ->call('share', $form->id)
        ->set('isPublic', true)
        ->set('closesOn', 'قريباً')
        ->call('saveSharing')
        ->assertHasErrors(['closesOn']);

    expect($form->refresh()->is_public)->toBeFalse();
});

/**
 * The screen the answers land on has to open for whoever is reading them.
 *
 * The responses screen was written for a supervisor and then hung on the
 * manager's and the teacher's routes unchanged, so every `guard('supervisor')`
 * inside it answered null for them. A manager who opened a form's responses got
 * an exception — which meant the applications this whole link exists to collect
 * were arriving in a page the person collecting them could not open.
 */
it('opens the responses screen for a manager, not only a supervisor', function () {
    $owner = Supervisor::factory()->create();
    $form = formOwnedBy($owner, ['is_supervisor_shared' => true]);

    Livewire::actingAs(Manager::factory()->create(), 'manager')
        ->test('supervisor.form-responses', ['formId' => $form->id])
        ->assertSuccessful();

    Livewire::actingAs($owner, 'supervisor')
        ->test('supervisor.form-responses', ['formId' => $form->id])
        ->assertSuccessful();
});

/**
 * And the buttons on the forms screen have to lead to the reader's own routes.
 *
 * Every link was written as `supervisor.forms.*`, so a manager who pressed
 * «الردود» was sent to a supervisor's URL, refused by the guard, and landed on
 * the sign-in page of the account he was already signed into.
 */
it('sends each reader to the routes of the role they are acting in', function () {
    $owner = Supervisor::factory()->create();
    $form = formOwnedBy($owner, ['is_supervisor_shared' => true]);

    Livewire::actingAs(Manager::factory()->create(), 'manager')
        ->test('supervisor.manage-forms')
        ->assertSee(route('manager.forms.responses', $form->id), false)
        ->assertDontSee(route('supervisor.forms.responses', $form->id), false);

    Livewire::actingAs($owner, 'supervisor')
        ->test('supervisor.manage-forms')
        ->assertSee(route('supervisor.forms.responses', $form->id), false);
});
