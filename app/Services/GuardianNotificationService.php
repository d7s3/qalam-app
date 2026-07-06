<?php

namespace App\Services;

use App\Jobs\SendGuardianWhatsappJob;
use App\Models\GuardianNotification;
use App\Models\Leaderboard;
use App\Models\Student;
use Carbon\Carbon;

class GuardianNotificationService
{
    /**
     * Record an in-app guardian notification and best-effort push it over WhatsApp.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(
        int $guardianId,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?int $studentId = null,
        ?string $senderClientId = null,
    ): GuardianNotification {
        $notification = GuardianNotification::create([
            'guardian_id' => $guardianId,
            'student_id' => $studentId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        self::pushWhatsapp($notification, $senderClientId);

        return $notification;
    }

    /**
     * Notify a student's guardian about an absence or late arrival, skipping if the
     * student has no linked guardian or the same alert was already recorded today.
     * An explicit sender session (e.g. the supervisor broadcasting manually) takes
     * precedence over the auto-resolved one.
     */
    public static function notifyAbsence(Student $student, string $status, string $date, ?string $senderClientId = null): ?GuardianNotification
    {
        if (! in_array($status, ['absent', 'late'], true) || ! $student->guardian_id) {
            return null;
        }

        $type = $status === 'late' ? 'late' : 'absence';

        $alreadyNotified = GuardianNotification::where('guardian_id', $student->guardian_id)
            ->where('student_id', $student->id)
            ->where('type', $type)
            ->where('data->date', $date)
            ->exists();

        if ($alreadyNotified) {
            return null;
        }

        $parts = self::absenceMessageParts($student, $status, $date);

        return self::record(
            guardianId: $student->guardian_id,
            type: $type,
            title: $parts['title'],
            body: $parts['body'],
            data: ['date' => $date, 'status' => $status],
            studentId: $student->id,
            senderClientId: $senderClientId ?? self::resolveWhatsappSender($student),
        );
    }

    /**
     * Build the absence/late alert title and body shared by the automatic
     * notification and the supervisor's manual broadcast. The date is shown in
     * Hijri as "weekday day month" without the year.
     *
     * @return array{title: string, body: string}
     */
    public static function absenceMessageParts(Student $student, string $status, string $date): array
    {
        $statusText = $status === 'late' ? 'متأخراً' : 'غائباً';
        $hijriDate = self::formatHijriDayMonth($date);

        return [
            'title' => $status === 'late' ? 'تنبيه تأخّر' : 'تنبيه غياب',
            'body' => "سُجِّل ابنكم {$student->name} {$statusText} يوم {$hijriDate}.",
        ];
    }

    /**
     * Format a Y-m-d Gregorian date as a Hijri "weekday day month" string
     * (e.g. "الأربعاء 24 ذو الحجة"), falling back to the raw date on failure.
     */
    public static function formatHijriDayMonth(string $date): string
    {
        try {
            $formatter = new \IntlDateFormatter(
                'ar_SA@calendar=islamic-umalqura',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::NONE,
                'Asia/Riyadh',
                \IntlDateFormatter::TRADITIONAL,
                'EEEE d MMMM'
            );

            return $formatter->format(Carbon::parse($date, 'Asia/Riyadh')->timestamp);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Resolve the WhatsApp sender session for a student via the supervisor of their
     * circle's active gamification competition. Returns null when none is available.
     */
    public static function resolveWhatsappSender(Student $student): ?string
    {
        if (! $student->circle_id) {
            return null;
        }

        $leaderboard = Leaderboard::whereHas('circles', fn ($q) => $q->where('circles.id', $student->circle_id))
            ->whereNotNull('supervisor_id')
            ->where('is_active', true)
            ->latest()
            ->first();

        return $leaderboard ? 'supervisor_'.$leaderboard->supervisor_id : null;
    }

    /**
     * Queue the WhatsApp push for a notification, only when the guardian has a phone
     * and a sender session was resolved. Failures are swallowed by the job itself.
     */
    private static function pushWhatsapp(GuardianNotification $notification, ?string $senderClientId): void
    {
        if (! $senderClientId) {
            return;
        }

        $guardian = $notification->guardian;
        if (! $guardian || ! $guardian->phone) {
            return;
        }

        SendGuardianWhatsappJob::dispatch(
            $guardian->phone,
            $notification->title."\n".$notification->body,
            $senderClientId,
        );
    }
}
