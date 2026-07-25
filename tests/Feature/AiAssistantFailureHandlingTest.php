<?php

use App\Ai\Agents\PersonlanAssistant;
use App\Models\AiInsight;
use App\Models\Manager;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = Manager::factory()->create();
    $this->actingAs($this->manager, 'manager');
});

it('fails over to a second provider when the first one is rate limited', function () {
    $reflection = new ReflectionClass(PersonlanAssistant::class);
    $providers = $reflection->getAttributes(Provider::class)[0]
        ->newInstance()->value;

    expect($providers)->toBe([Lab::Gemini, Lab::DeepSeek]);
});

it('shows a rate limit notice instead of failing when generating insights', function () {
    AiInsight::create([
        'period' => '2026-07',
        'category' => 'الطلاب',
        'title' => 'تحليل سابق',
        'description' => 'يجب أن يبقى كما هو',
        'type' => 'neutral',
    ]);

    PersonlanAssistant::fake(function () {
        throw RateLimitedException::forProvider('gemini', 429);
    });

    Livewire::test('manager.ai-analysis')
        ->set('period', '2026-07')
        ->call('generateInsights')
        ->assertHasNoErrors();

    // The existing analysis must survive a failed refresh.
    expect(AiInsight::where('period', '2026-07')->count())->toBe(1)
        ->and(AiInsight::first()->title)->toBe('تحليل سابق');
});

it('keeps the existing insights when the provider errors', function () {
    AiInsight::create([
        'period' => '2026-07',
        'category' => 'الحلقات',
        'title' => 'تحليل سابق',
        'description' => 'يجب أن يبقى كما هو',
        'type' => 'positive',
    ]);

    PersonlanAssistant::fake(function () {
        throw new AiException('provider exploded');
    });

    Livewire::test('manager.ai-analysis')
        ->set('period', '2026-07')
        ->call('generateInsights')
        ->assertHasNoErrors();

    expect(AiInsight::where('period', '2026-07')->count())->toBe(1);
});

it('discards an unparsable analysis rather than wiping the saved one', function () {
    AiInsight::create([
        'period' => '2026-07',
        'category' => 'الطلاب',
        'title' => 'تحليل سابق',
        'description' => 'يجب أن يبقى كما هو',
        'type' => 'neutral',
    ]);

    PersonlanAssistant::fake(['this is not json at all']);

    Livewire::test('manager.ai-analysis')
        ->set('period', '2026-07')
        ->call('generateInsights')
        ->assertHasNoErrors();

    expect(AiInsight::where('period', '2026-07')->count())->toBe(1)
        ->and(AiInsight::first()->title)->toBe('تحليل سابق');
});

it('replaces the insights when the analysis parses', function () {
    AiInsight::create([
        'period' => '2026-07',
        'category' => 'الطلاب',
        'title' => 'تحليل قديم',
        'description' => 'سيُستبدل',
        'type' => 'neutral',
    ]);

    PersonlanAssistant::fake([json_encode([[
        'category' => 'الحلقات',
        'title' => 'أعلى الحلقات التزاماً',
        'description' => 'حلقة النور هي الأعلى حضوراً هذا الشهر.',
        'type' => 'positive',
    ]], JSON_UNESCAPED_UNICODE)]);

    Livewire::test('manager.ai-analysis')
        ->set('period', '2026-07')
        ->call('generateInsights')
        ->assertHasNoErrors();

    $insights = AiInsight::where('period', '2026-07')->get();

    expect($insights)->toHaveCount(1)
        ->and($insights->first()->title)->toBe('أعلى الحلقات التزاماً')
        ->and($insights->first()->type)->toBe('positive');
});

it('answers with a readable notice when the chat hits a rate limit', function () {
    PersonlanAssistant::fake(function () {
        throw RateLimitedException::forProvider('gemini', 429);
    });

    $component = Livewire::test('manager.ai-assistant')
        ->set('input', 'كم عدد الطلاب؟')
        ->call('ask')
        ->assertHasNoErrors();

    $messages = $component->get('messages');
    $reply = end($messages);

    expect($reply['role'])->toBe('ai')
        ->and($reply['content'])->toContain('تجاوزت حصة الطلبات')
        ->and($messages[count($messages) - 2]['content'])->toBe('كم عدد الطلاب؟');
});

it('answers with a readable notice when the chat errors for any other reason', function () {
    PersonlanAssistant::fake(function () {
        throw new RuntimeException('a tool blew up');
    });

    $component = Livewire::test('manager.ai-assistant')
        ->set('input', 'كم عدد الطلاب؟')
        ->call('ask')
        ->assertHasNoErrors();

    $messages = $component->get('messages');

    expect(end($messages)['content'])->toContain('حدث خطأ أثناء تجهيز الإجابة');
});

it('ignores an empty chat submission', function () {
    Livewire::test('manager.ai-assistant')
        ->set('input', '   ')
        ->call('ask')
        ->assertCount('messages', 1);
});
