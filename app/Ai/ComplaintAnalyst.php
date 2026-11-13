<?php

namespace App\Ai;

use App\Models\AppComplaint;
use App\Models\StudentEvent;
use App\Support\AiSettings;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Reads a complaint sent to the Telegram bot — its explanation, its
 * screenshot, and what the platform recorded for the sender — and sorts it,
 * so whoever fixes it starts from a diagnosis rather than a message.
 */
class ComplaintAnalyst implements Agent
{
    use Promptable;

    public const KINDS = ['خطأ في المنصة', 'صعوبة في الاستخدام', 'اقتراح', 'مشكلة بيانات', 'أخرى'];

    public const SEVERITIES = ['عاجلة', 'متوسطة', 'منخفضة'];

    /**
     * @return array<string, string|null>
     */
    public function provider(): array
    {
        AiSettings::apply();

        return AiSettings::providerChain();
    }

    public static function available(): bool
    {
        return AiSettings::hasKey(AiSettings::provider());
    }

    public function instructions(): Stringable|string
    {
        $kinds = implode('، ', self::KINDS);
        $severities = implode('، ', self::SEVERITIES);

        return <<<INSTRUCTIONS
        You triage complaints about «قلم», an Arabic web platform for a Quran and
        Islamic studies academy. Students record attendance, memorisation, a weekly
        self-study programme (البرنامج الذاتي) and their reading; teachers and
        supervisors manage circles, plans and reports.

        You get the sender's role, his explanation, often a screenshot, and any
        errors the platform recorded for him recently. Return ONLY a JSON object,
        no prose, no markdown fence, with these keys, all values in Arabic:
          "kind"          one of: {$kinds}
          "severity"      one of: {$severities} — عاجلة when he cannot do his work at all
          "page"          the page or feature concerned, as the platform would name it
          "summary"       one sentence: what goes wrong
          "likely_cause"  your best guess at why, or "غير واضح"
          "next_step"     what the developer should check or change first
          "reply"         a short, kind reply to the sender, without promises of dates

        Do not invent details the complaint does not contain.
        INSTRUCTIONS;
    }

    /**
     * The analysis, or null when no provider could be asked or its reply was unreadable.
     *
     * @return array<string, string>|null
     */
    public static function analyse(AppComplaint $complaint): ?array
    {
        if (! self::available()) {
            return null;
        }

        $attachments = $complaint->media_type === 'photo' && $complaint->media_path
            ? [Image::fromStorage($complaint->media_path, disk: 'local')]
            : [];

        try {
            $reply = (string) (new self)->prompt(self::describe($complaint), attachments: $attachments);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return self::sanitize($reply);
    }

    /** What the model is told about the complaint. */
    public static function describe(AppComplaint $complaint): string
    {
        $lines = [
            "الدور: {$complaint->role}",
            'المرفق: '.($complaint->media_type === 'video' ? 'مقطع فيديو (لا تراه)' : 'لقطة شاشة'),
            'الشرح: '.($complaint->description ?: 'لم يكتب شرحاً'),
        ];

        if ($complaint->user_id) {
            $problems = StudentEvent::where('student_id', $complaint->user_id)
                ->where('kind', StudentEvent::PROBLEM)
                ->where('created_at', '>=', now()->subDays(7))
                ->latest('created_at')
                ->limit(5)
                ->get(['route_name', 'detail'])
                ->map(fn (StudentEvent $event) => "- {$event->route_name}: ".mb_substr((string) $event->detail, 0, 200));

            if ($problems->isNotEmpty()) {
                $lines[] = "أخطاء سجّلتها المنصة له في آخر أسبوع:\n".$problems->implode("\n");
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Keep only the keys asked for, as short strings.
     *
     * @return array<string, string>|null
     */
    public static function sanitize(string $reply): ?array
    {
        $reply = trim($reply);

        if (preg_match('/\{.*\}/s', $reply, $match)) {
            $reply = $match[0];
        }

        $decoded = json_decode($reply, true);

        if (! is_array($decoded)) {
            return null;
        }

        $analysis = [];

        foreach (['kind', 'severity', 'page', 'summary', 'likely_cause', 'next_step', 'reply'] as $key) {
            $value = $decoded[$key] ?? null;
            $analysis[$key] = is_string($value) ? mb_substr(trim($value), 0, 600) : '';
        }

        $analysis['kind'] = in_array($analysis['kind'], self::KINDS, true) ? $analysis['kind'] : 'أخرى';
        $analysis['severity'] = in_array($analysis['severity'], self::SEVERITIES, true) ? $analysis['severity'] : 'متوسطة';

        return $analysis;
    }
}
