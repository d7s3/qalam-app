<?php

namespace App\Jobs;

use App\Ai\ComplaintAnalyst;
use App\Models\AppComplaint;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keep a new complaint's screenshot or clip, have it analysed, and tell the
 * manager on Telegram what came in.
 */
class AnalyseAppComplaint implements ShouldQueue
{
    use Queueable;

    /** A clip can take a while to download and a model a while to answer. */
    public int $timeout = 180;

    public function __construct(public AppComplaint $complaint) {}

    public function handle(TelegramService $telegram): void
    {
        $complaint = $this->complaint;

        if ($telegram->configured() && $complaint->media_file_id && ! $complaint->media_path) {
            $complaint->update(['media_path' => $telegram->download($complaint->media_file_id, "complaints/{$complaint->id}")]);
        }

        $complaint->update(['analysis' => ComplaintAnalyst::analyse($complaint)]);

        $manager = (string) config('services.telegram.admin_chat_id');

        if (! $telegram->configured() || $manager === '') {
            return;
        }

        $telegram->send($manager, self::report($complaint));

        if ($complaint->media_message_id) {
            $telegram->copy($manager, $complaint->chat_id, $complaint->media_message_id);
        }
    }

    /** The message the manager reads. */
    public static function report(AppComplaint $complaint): string
    {
        $who = $complaint->name.' — '.$complaint->role
            .($complaint->telegram_username ? " (@{$complaint->telegram_username})" : '')
            .($complaint->user_id ? '' : ' — لم أجد اسمه في المنصة');

        $lines = [
            "شكوى رقم {$complaint->id}",
            $who,
            '',
            'الشرح: '.($complaint->description ?: '— (مقطع بلا شرح)'),
        ];

        $analysis = $complaint->analysis;

        if ($analysis) {
            array_push($lines,
                '',
                "التصنيف: {$analysis['kind']} — {$analysis['severity']}",
                "الصفحة: {$analysis['page']}",
                "الخلاصة: {$analysis['summary']}",
                "السبب المرجّح: {$analysis['likely_cause']}",
                "الخطوة التالية: {$analysis['next_step']}",
                "رد مقترح للمرسل: {$analysis['reply']}",
            );
        } else {
            array_push($lines, '', 'لم يُحلَّل آلياً (لا مزوّد ذكاء اصطناعي متاح أو تعذّر الرد).');
        }

        return mb_substr(implode("\n", $lines), 0, 4000);
    }
}
