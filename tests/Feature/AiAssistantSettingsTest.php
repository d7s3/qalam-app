<?php

use App\Ai\Agents\PersonlanAssistant;
use App\Models\AiProviderCredential;
use App\Models\Manager;
use App\Models\Setting;
use App\Support\AiSettings;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = Manager::factory()->create();
    $this->actingAs($this->manager, 'manager');
});

it('lets the manager choose the provider and model for the assistant', function () {
    Livewire::test('manager.ai-assistant-settings')
        ->set('provider', 'anthropic')
        ->set('model', 'claude-opus-4-6')
        ->set('fallbackProvider', 'deepseek')
        ->call('save')
        ->assertHasNoErrors();

    expect(AiSettings::provider())->toBe('anthropic')
        ->and(AiSettings::model())->toBe('claude-opus-4-6')
        ->and(AiSettings::fallbackProvider())->toBe('deepseek');
});

it('hands the SDK a provider-keyed chain so the chosen model survives failover', function () {
    Setting::setVal(AiSettings::PROVIDER_KEY, 'anthropic');
    Setting::setVal(AiSettings::MODEL_KEY, 'claude-opus-4-6');
    Setting::setVal(AiSettings::FALLBACK_PROVIDER_KEY, 'deepseek');

    // A plain list would make the SDK drop the model; the keys carry it through.
    expect((new PersonlanAssistant)->provider())->toBe([
        'anthropic' => 'claude-opus-4-6',
        'deepseek' => null,
    ]);
});

it('asks for the provider default when no model is chosen', function () {
    Setting::setVal(AiSettings::PROVIDER_KEY, 'gemini');
    Setting::setVal(AiSettings::MODEL_KEY, '');
    Setting::setVal(AiSettings::FALLBACK_PROVIDER_KEY, '');

    expect(AiSettings::model())->toBeNull()
        ->and((new PersonlanAssistant)->provider())->toBe(['gemini' => null]);
});

it('rejects a provider the sdk cannot generate text with', function () {
    Livewire::test('manager.ai-assistant-settings')
        ->set('provider', 'elevenlabs')
        ->call('save')
        ->assertHasErrors('provider');

    expect(Setting::getVal(AiSettings::PROVIDER_KEY))->toBeNull();
});

it('ignores a stored provider that is no longer supported', function () {
    Setting::setVal(AiSettings::PROVIDER_KEY, 'some-retired-provider');

    expect(AiSettings::provider())->toBe(config('ai.default'));
});

it('drops a fallback that is the same as the primary provider', function () {
    Livewire::test('manager.ai-assistant-settings')
        ->set('provider', 'gemini')
        ->set('fallbackProvider', 'gemini')
        ->call('save')
        ->assertHasNoErrors();

    expect(AiSettings::fallbackProvider())->toBeNull()
        ->and((new PersonlanAssistant)->provider())->toBe(['gemini' => null]);
});

it('stores a provider key encrypted and never renders it back', function () {
    Livewire::test('manager.ai-assistant-settings')
        ->set('newKeys.anthropic', 'sk-ant-super-secret-1234')
        ->call('saveKey', 'anthropic')
        ->assertHasNoErrors()
        ->assertSet('newKeys.anthropic', '')
        ->assertDontSee('sk-ant-super-secret-1234')
        ->assertSee('••••••••1234');

    $credential = AiProviderCredential::where('provider', 'anthropic')->first();

    expect($credential->api_key)->toBe('sk-ant-super-secret-1234');

    // The column itself must not hold the plain key.
    $raw = DB::table('ai_provider_credentials')->where('provider', 'anthropic')->value('api_key');

    expect($raw)->not->toBe('sk-ant-super-secret-1234')
        ->and($raw)->not->toContain('super-secret');
});

it('keeps the stored key when the input is left blank', function () {
    AiProviderCredential::create(['provider' => 'openai', 'api_key' => 'sk-original']);

    Livewire::test('manager.ai-assistant-settings')
        ->set('baseUrls.openai', 'https://proxy.example.test/v1')
        ->call('saveKey', 'openai')
        ->assertHasNoErrors();

    $credential = AiProviderCredential::where('provider', 'openai')->first();

    expect($credential->api_key)->toBe('sk-original')
        ->and($credential->base_url)->toBe('https://proxy.example.test/v1');
});

it('lets the manager delete a stored key', function () {
    AiProviderCredential::create(['provider' => 'groq', 'api_key' => 'gsk-old']);

    Livewire::test('manager.ai-assistant-settings')
        ->call('deleteKey', 'groq')
        ->assertHasNoErrors();

    expect(AiProviderCredential::where('provider', 'groq')->exists())->toBeFalse();
});

it('refuses to touch a provider outside the supported list', function () {
    Livewire::test('manager.ai-assistant-settings')
        ->set('newKeys.elevenlabs', 'sk-nope')
        ->call('saveKey', 'elevenlabs')
        ->assertStatus(404);

    expect(AiProviderCredential::count())->toBe(0);
});

it('feeds the stored key to the sdk configuration when the assistant runs', function () {
    config(['ai.providers.anthropic.key' => null]);

    AiProviderCredential::create(['provider' => 'anthropic', 'api_key' => 'sk-from-database']);
    Setting::setVal(AiSettings::PROVIDER_KEY, 'anthropic');

    (new PersonlanAssistant)->provider();

    expect(config('ai.providers.anthropic.key'))->toBe('sk-from-database');
});

it('leaves the environment key in place when the database has none', function () {
    config(['ai.providers.gemini.key' => 'key-from-env']);

    AiSettings::apply();

    expect(config('ai.providers.gemini.key'))->toBe('key-from-env')
        ->and(AiSettings::hasKey('gemini'))->toBeTrue();
});

it('warns on save when the chosen provider has no key at all', function () {
    config(['ai.providers.groq.key' => null]);

    Livewire::test('manager.ai-assistant-settings')
        ->set('provider', 'groq')
        ->call('save')
        ->assertHasNoErrors();

    expect(AiSettings::hasKey('groq'))->toBeFalse()
        ->and(AiSettings::provider())->toBe('groq');
});

/**
 * The SDK ships "deepseek-chat" as this provider's default, which the DeepSeek
 * API rejects outright with a 400. A stale name here silently breaks the
 * provider for anyone who does not type a model by hand.
 */
it('pins deepseek to model names its api still accepts', function () {
    $accepted = ['deepseek-v4-flash', 'deepseek-v4-pro'];

    expect(config('ai.providers.deepseek.models.text.default'))->toBeIn($accepted)
        ->and(config('ai.providers.deepseek.models.text.smartest'))->toBeIn($accepted)
        ->and(AiSettings::TEXT_PROVIDERS['deepseek']['models'])->toBe($accepted);
});

it('shows the settings page to a manager', function () {
    $this->get(route('manager.ai-settings'))
        ->assertOk()
        ->assertSee('موديل المساعد الذكي')
        ->assertSee('مفاتيح المزوّدين');
});

it('keeps the settings page away from other roles', function () {
    auth('manager')->logout();

    $this->get(route('manager.ai-settings'))->assertRedirect();
});
