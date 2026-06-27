<?php

namespace App\Services;

use App\Models\GamificationNews;
use App\Models\GamificationStudentState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GamificationNewsService
{
    /**
     * Record a news/digest event for a competition.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(int $leaderboardId, string $type, array $data, ?string $eventDate = null): GamificationNews
    {
        return GamificationNews::create([
            'leaderboard_id' => $leaderboardId,
            'type' => $type,
            'event_date' => $eventDate ?: Carbon::today()->toDateString(),
            'data' => $data,
        ]);
    }

    /**
     * Detect a student level-up and record it. A baseline is stored silently the
     * first time so existing levels are not announced retroactively.
     */
    public static function syncStudentLevel(int $studentId, int $leaderboardId): void
    {
        $state = GamificationStudentState::where('student_id', $studentId)
            ->where('leaderboard_id', $leaderboardId)
            ->first();

        if (! $state) {
            return;
        }

        $levelInfo = GamificationService::getStudentLevel($studentId, $leaderboardId);
        $currentLevel = (int) ($levelInfo['current']->level_number ?? 1);

        if ($state->notified_level === null) {
            $state->notified_level = $currentLevel;
            $state->saveQuietly();

            return;
        }

        if ($currentLevel > $state->notified_level) {
            $student = $state->student;
            self::record($leaderboardId, 'level_up', [
                'student_id' => $studentId,
                'student_name' => $student?->name ?? '',
                'level' => $currentLevel,
                'level_name' => $levelInfo['current']->name ?? "المستوى {$currentLevel}",
            ]);

            $state->notified_level = $currentLevel;
            $state->saveQuietly();
        }
    }

    /**
     * Get a single day's digest grouped by event type.
     *
     * @return array<string, Collection<int, GamificationNews>>
     */
    public static function getDailyDigest(int $leaderboardId, string $date): array
    {
        return GamificationNews::where('leaderboard_id', $leaderboardId)
            ->whereDate('event_date', $date)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('type')
            ->all();
    }

    /**
     * Distinct dates (newest first) that have news for the competition.
     *
     * @return array<int, string>
     */
    public static function getAvailableDates(int $leaderboardId): array
    {
        return GamificationNews::where('leaderboard_id', $leaderboardId)
            ->orderByDesc('event_date')
            ->pluck('event_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->all();
    }
}
