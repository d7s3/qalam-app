<?php

use App\Ai\ComplaintAnalyst;
use App\Jobs\AnalyseAppComplaint;
use App\Models\AppComplaint;
use App\Models\Student;
use App\Services\ComplaintBot;
use App\Services\TelegramService;
use App\Support\AiSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Asked for by Faisal, 12 Nov 2026: a Telegram bot that gathers complaints
 * about the platform — a name, a role, and a screenshot with its explanation
 * or a clip — and has them analysed.
 */
beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.webhook_secret' => 'secret-abc',
        'services.telegram.admin_chat_id' => '999',
    ]);

    Http::fake([
        'api.telegram.org/bot*/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/a.jpg', 'file_size' => 1000]]),
        'api.telegram.org/file/*' => Http::response('jpeg-bytes'),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
    ]);
    Bus::fake();
});

/**
 * Post a private message from chat 555 as Telegram would.
 *
 * @param  array<string, mixed>  $message
 */
function telegramSays(array $message, string $secret = 'secret-abc'): TestResponse
{
    static $id = 0;

    return test()->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)
        ->postJson(route('telegram.webhook', $secret), ['update_id' => ++$id, 'message' => $message + [
            'message_id' => 100 + $id,
            'chat' => ['id' => 555, 'type' => 'private'],
            'from' => ['id' => 555, 'username' => 'omar'],
        ]]);
}

/** What the bot last said to a chat. */
function botLastSaid(string $chatId = '555'): ?string
{
    return collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => str_ends_with($request->url(), '/sendMessage') && (string) $request['chat_id'] === $chatId)
        ->map(fn (Request $request) => $request['text'])
        ->last();
}

it('asks for a name, a role and a screenshot with its explanation', function () {
    $student = Student::factory()->create(['name' => 'عمر محمد العتيبي']);

    telegramSays(['text' => '/start'])->assertOk();
    expect(botLastSaid())->toContain('ما اسمك');

    telegramSays(['text' => 'عمر محمد العتيبي']);
    expect(botLastSaid())->toContain('ما دورك');

    telegramSays(['text' => 'طالب']);
    expect(botLastSaid())->toContain('لقطة شاشة');

    telegramSays(['text' => 'لا يحفظ']);
    expect(botLastSaid())->toContain('نحتاج صورة');

    telegramSays(['photo' => [['file_id' => 'small'], ['file_id' => 'large']]]);
    expect(botLastSaid())->toContain('اكتب الآن شرح المشكلة');

    telegramSays(['text' => 'أضغط حفظ في البرنامج الذاتي ولا يحفظ']);
    expect(botLastSaid())->toContain('وصلت ملاحظتك');

    $complaint = AppComplaint::sole();

    expect($complaint)
        ->step->toBe(AppComplaint::STEP_DONE)
        ->name->toBe('عمر محمد العتيبي')
        ->role->toBe('طالب')
        ->user_id->toBe($student->id)
        ->media_type->toBe('photo')
        ->media_file_id->toBe('large')
        ->description->toBe('أضغط حفظ في البرنامج الذاتي ولا يحفظ');

    Bus::assertDispatchedAfterResponse(AnalyseAppComplaint::class);
});

it('takes a clip without an explanation, and a returning sender is not asked again', function () {
    AppComplaint::factory()->create(['chat_id' => '555', 'name' => 'خالد', 'role' => 'معلم']);

    telegramSays(['video' => ['file_id' => 'clip'], 'caption' => '']);

    $complaint = AppComplaint::latest('id')->first();

    expect($complaint)
        ->step->toBe(AppComplaint::STEP_DONE)
        ->name->toBe('خالد')
        ->role->toBe('معلم')
        ->media_type->toBe('video')
        ->and(botLastSaid())->toContain('وصلت ملاحظتك');
});

it('refuses a role that is not offered', function () {
    telegramSays(['text' => '/start']);
    telegramSays(['text' => 'عمر محمد']);
    telegramSays(['text' => 'مدير الكون']);

    expect(botLastSaid())->toContain('اختر دورك')
        ->and(AppComplaint::sole()->step)->toBe(AppComplaint::STEP_ROLE);
});

it('stops a chat after ten complaints in a day', function () {
    AppComplaint::factory()->count(ComplaintBot::DAILY_LIMIT)->create(['chat_id' => '555']);

    telegramSays(['text' => 'شكوى جديدة']);

    expect(botLastSaid())->toContain('تستطيع إرسال المزيد غداً')
        ->and(AppComplaint::count())->toBe(ComplaintBot::DAILY_LIMIT);
});

it('ignores a post without Telegram\'s secret', function () {
    test()->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong')
        ->postJson(route('telegram.webhook', 'secret-abc'), ['message' => ['text' => '/start', 'chat' => ['id' => 1, 'type' => 'private']]])
        ->assertNotFound();

    telegramSays(['text' => '/start'], 'guess')->assertNotFound();

    expect(AppComplaint::count())->toBe(0);
});

it('ignores groups', function () {
    test()->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-abc')
        ->postJson(route('telegram.webhook', 'secret-abc'), ['message' => ['text' => '/start', 'chat' => ['id' => -5, 'type' => 'group']]])
        ->assertOk();

    expect(AppComplaint::count())->toBe(0);
});

it('keeps the screenshot, analyses it and tells the manager', function () {
    Storage::fake('local');
    config(['ai.providers.'.AiSettings::provider().'.key' => 'test-key']);

    ComplaintAnalyst::fake([json_encode([
        'kind' => 'خطأ في المنصة',
        'severity' => 'عاجلة',
        'page' => 'البرنامج الذاتي',
        'summary' => 'زر الحفظ لا يحفظ',
        'likely_cause' => 'غير واضح',
        'next_step' => 'جرّب الحفظ بحساب طالب',
        'reply' => 'شكراً، نعمل عليها',
    ])]);

    $complaint = AppComplaint::factory()->create(['chat_id' => '555', 'media_message_id' => 42]);

    (new AnalyseAppComplaint($complaint))->handle(app(TelegramService::class));

    $complaint->refresh();

    expect($complaint->media_path)->toBe("complaints/{$complaint->id}.jpg")
        ->and(Storage::disk('local')->get($complaint->media_path))->toBe('jpeg-bytes')
        ->and($complaint->analysis['severity'])->toBe('عاجلة')
        ->and(botLastSaid('999'))->toContain("شكوى رقم {$complaint->id}")->toContain('زر الحفظ لا يحفظ');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/copyMessage')
        && $request['chat_id'] === '999' && $request['message_id'] === 42);
});

it('keeps only what it asked for from the analysis', function () {
    expect(ComplaintAnalyst::sanitize('```json {"kind":"شيء آخر","severity":"قصوى","summary":"س","extra":"x"} ```'))
        ->toMatchArray(['kind' => 'أخرى', 'severity' => 'متوسطة', 'summary' => 'س', 'page' => ''])
        ->not->toHaveKey('extra')
        ->and(ComplaintAnalyst::sanitize('لا أعرف'))->toBeNull();
});

it('points the bot at the site', function () {
    $this->artisan('telegram:set-webhook')->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/setWebhook')
        && $request['url'] === route('telegram.webhook', 'secret-abc')
        && $request['secret_token'] === 'secret-abc');
});
