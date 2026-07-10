<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Teacher;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Record an in-app notification for a single recipient.
     */
    public static function notify(
        string $recipientType,
        int $recipientId,
        string $type,
        string $title,
        string $body,
        ?string $url = null,
        array $data = [],
    ): AppNotification {
        return AppNotification::create([
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'data' => $data,
        ]);
    }

    /**
     * Notify every teacher assigned to the given circle.
     */
    public static function notifyCircleTeachers(int $circleId, string $type, string $title, string $body, ?string $url = null, array $data = []): void
    {
        $teacherIds = Teacher::whereHas('circles', fn ($q) => $q->where('circles.id', $circleId))->pluck('id');

        foreach ($teacherIds as $teacherId) {
            self::notify('teacher', $teacherId, $type, $title, $body, $url, $data);
        }
    }

    public static function unreadCountFor(string $recipientType, int $recipientId): int
    {
        return AppNotification::where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->whereNull('read_at')
            ->count();
    }

    /** @return Collection<int, AppNotification> */
    public static function recentFor(string $recipientType, int $recipientId, int $limit = 8): Collection
    {
        return AppNotification::where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public static function markAllRead(string $recipientType, int $recipientId): void
    {
        AppNotification::where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
