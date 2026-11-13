<?php

namespace App\Services;

use App\Jobs\AnalyseAppComplaint;
use App\Models\AppComplaint;
use App\Models\User;

/**
 * The complaints bot's conversation: a name, a role, then a screenshot with
 * its explanation or a clip of the problem — one answer at a time.
 */
class ComplaintBot
{
    /** Complaints one chat may send in a day, so the bot cannot be flooded. */
    public const DAILY_LIMIT = 10;

    public function __construct(private TelegramService $telegram) {}

    /**
     * @param  array<string, mixed>  $update  Telegram's update, as posted to the webhook.
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;

        // Groups and channels are not where complaints are sent.
        if (! is_array($message) || ($message['chat']['type'] ?? null) !== 'private') {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '/myid') {
            $this->telegram->send($chatId, "رقم محادثتك: {$chatId}");

            return;
        }

        $draft = AppComplaint::where('chat_id', $chatId)->where('step', '!=', AppComplaint::STEP_DONE)->latest('id')->first();

        if ($draft === null || str_starts_with($text, '/start') || $text === 'شكوى جديدة') {
            $this->begin($chatId, $message, $draft);

            return;
        }

        match ($draft->step) {
            AppComplaint::STEP_NAME => $this->takeName($draft, $text),
            AppComplaint::STEP_ROLE => $this->takeRole($draft, $text),
            AppComplaint::STEP_MEDIA => $this->takeMedia($draft, $message),
            AppComplaint::STEP_DESCRIPTION => $this->takeDescription($draft, $text),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function begin(string $chatId, array $message, ?AppComplaint $draft): void
    {
        $sentToday = AppComplaint::where('chat_id', $chatId)
            ->where('step', AppComplaint::STEP_DONE)
            ->where('submitted_at', '>=', today())
            ->count();

        if ($sentToday >= self::DAILY_LIMIT) {
            $this->telegram->send($chatId, 'وصلتنا شكاواك اليوم، شكراً لك. تستطيع إرسال المزيد غداً.');

            return;
        }

        $draft?->delete();

        // A returning sender is not asked his name and role again.
        $last = AppComplaint::where('chat_id', $chatId)->where('step', AppComplaint::STEP_DONE)->latest('id')->first();

        $complaint = AppComplaint::create([
            'chat_id' => $chatId,
            'telegram_username' => $message['from']['username'] ?? null,
            'name' => $last?->name,
            'role' => $last?->role,
            'user_id' => $last?->user_id,
            'step' => $last ? AppComplaint::STEP_MEDIA : AppComplaint::STEP_NAME,
        ]);

        // He sent the screenshot straight away; take it rather than ask for it.
        if ($last && ! isset($message['text'])) {
            $this->takeMedia($complaint, $message);

            return;
        }

        if ($last) {
            $this->askForMedia($complaint, "أهلاً {$last->name}، ");

            return;
        }

        $this->telegram->send($chatId, "أهلاً بك في بوت ملاحظات منصة قلم.\nنستقبل هنا ما يواجهك من مشكلات في المنصة لنصلحها.\n\nما اسمك الثلاثي كما هو في المنصة؟", ['remove_keyboard' => true]);
    }

    private function takeName(AppComplaint $complaint, string $text): void
    {
        $name = preg_replace('/\s+/u', ' ', $text);

        if (mb_strlen($name) < 3 || mb_strlen($name) > 80 || str_starts_with($name, '/')) {
            $this->telegram->send($complaint->chat_id, 'اكتب اسمك من فضلك، مثل: عمر محمد العتيبي');

            return;
        }

        $matches = User::where('name', $name)->limit(2)->pluck('id');

        $complaint->update([
            'name' => $name,
            'user_id' => $matches->count() === 1 ? $matches->first() : null,
            'step' => AppComplaint::STEP_ROLE,
        ]);

        $this->telegram->send($complaint->chat_id, 'ما دورك في المنصة؟', [
            'keyboard' => collect(AppComplaint::ROLES)->map(fn (string $role) => [['text' => $role]])->all(),
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }

    private function takeRole(AppComplaint $complaint, string $text): void
    {
        if (! in_array($text, AppComplaint::ROLES, true)) {
            $this->telegram->send($complaint->chat_id, 'اختر دورك من الأزرار في الأسفل.');

            return;
        }

        $complaint->update(['role' => $text, 'step' => AppComplaint::STEP_MEDIA]);

        $this->askForMedia($complaint);
    }

    private function askForMedia(AppComplaint $complaint, string $greeting = ''): void
    {
        $this->telegram->send(
            $complaint->chat_id,
            $greeting.'أرسل لقطة شاشة للمشكلة واكتب شرحها في التعليق تحت الصورة، أو أرسل مقطعاً يُظهر المشكلة.',
            ['remove_keyboard' => true],
        );
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function takeMedia(AppComplaint $complaint, array $message): void
    {
        [$type, $fileId] = match (true) {
            ! empty($message['photo']) => ['photo', end($message['photo'])['file_id']],
            isset($message['video']) => ['video', $message['video']['file_id']],
            isset($message['video_note']) => ['video', $message['video_note']['file_id']],
            str_starts_with((string) ($message['document']['mime_type'] ?? ''), 'image/') => ['photo', $message['document']['file_id']],
            str_starts_with((string) ($message['document']['mime_type'] ?? ''), 'video/') => ['video', $message['document']['file_id']],
            default => [null, null],
        };

        if ($type === null) {
            $this->telegram->send($complaint->chat_id, 'نحتاج صورة للمشكلة مع شرحها، أو مقطعاً يُظهرها. أرسل أحدهما من فضلك.');

            return;
        }

        $caption = trim((string) ($message['caption'] ?? ''));

        $complaint->update([
            'media_type' => $type,
            'media_file_id' => $fileId,
            'media_message_id' => $message['message_id'] ?? null,
            'description' => $caption !== '' ? $caption : null,
        ]);

        // A photo is not understood without its explanation; a clip shows itself.
        if ($type === 'photo' && $caption === '') {
            $complaint->update(['step' => AppComplaint::STEP_DESCRIPTION]);
            $this->telegram->send($complaint->chat_id, 'وصلت الصورة. اكتب الآن شرح المشكلة: ماذا فعلت، وماذا كنت تنتظر أن يحدث؟');

            return;
        }

        $this->submit($complaint);
    }

    private function takeDescription(AppComplaint $complaint, string $text): void
    {
        if (mb_strlen($text) < 5) {
            $this->telegram->send($complaint->chat_id, 'اكتب شرح المشكلة بجملة واحدة على الأقل.');

            return;
        }

        $complaint->update(['description' => mb_substr($text, 0, 3000)]);

        $this->submit($complaint);
    }

    private function submit(AppComplaint $complaint): void
    {
        $complaint->update(['step' => AppComplaint::STEP_DONE, 'submitted_at' => now()]);

        $this->telegram->send(
            $complaint->chat_id,
            "وصلت ملاحظتك برقم {$complaint->id}، شكراً لك. سنعمل عليها.\nلإرسال ملاحظة أخرى اكتب: شكوى جديدة",
            ['keyboard' => [[['text' => 'شكوى جديدة']]], 'resize_keyboard' => true],
        );

        AnalyseAppComplaint::dispatch($complaint);
    }
}
