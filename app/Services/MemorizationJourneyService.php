<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Ayah;
use App\Models\Student;
use App\Models\StudentPlanDay;
use Carbon\Carbon;

class MemorizationJourneyService
{
    /**
     * Map every juz (1-30) to the student's memorization status: full, partial, none.
     * Computed by overlapping each juz's ayah-id range with the memorized range.
     *
     * @return array<int, array{juz: int, status: string}>
     */
    public static function juzMap(Student $student): array
    {
        $range = $student->getMemorizedRange();

        $juzBounds = Ayah::selectRaw('juz_number, MIN(id) as min_id, MAX(id) as max_id')
            ->groupBy('juz_number')
            ->get()
            ->keyBy('juz_number');

        $map = [];
        for ($juz = 1; $juz <= 30; $juz++) {
            $bounds = $juzBounds->get($juz);
            $status = 'none';

            if ($range && $bounds) {
                $overlaps = ! ($bounds->max_id < $range['min'] || $bounds->min_id > $range['max']);
                if ($overlaps) {
                    $status = ($bounds->min_id >= $range['min'] && $bounds->max_id <= $range['max']) ? 'full' : 'partial';
                }
            }

            $map[] = ['juz' => $juz, 'status' => $status];
        }

        return $map;
    }

    /**
     * The student's most recent hifz evaluations, oldest-first for a left-to-right
     * timeline.
     *
     * @return array<int, array{date: string, achievement: int}>
     */
    public static function scoreTrend(Student $student, int $limit = 12): array
    {
        return StudentPlanDay::whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
            ->whereNotNull('hifz_achievement')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['date', 'hifz_achievement'])
            ->reverse()
            ->map(fn ($day) => ['date' => $day->date->toDateString(), 'achievement' => (int) $day->hifz_achievement])
            ->values()
            ->all();
    }

    /**
     * Present/total attendance per week for the last N weeks, oldest-first.
     *
     * @return array<int, array{label: string, present: int, total: int}>
     */
    public static function attendanceTrend(Student $student, int $weeks = 8): array
    {
        $oldestStart = now()->startOfWeek(Carbon::SATURDAY)->subWeeks($weeks - 1);

        $records = Attendance::where('student_id', $student->id)
            ->where('date', '>=', $oldestStart)
            ->get(['date', 'status']);

        $buckets = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = now()->startOfWeek(Carbon::SATURDAY)->subWeeks($i);
            $buckets[$start->toDateString()] = ['label' => $start->format('m/d'), 'present' => 0, 'total' => 0];
        }

        foreach ($records as $record) {
            $weekStart = Carbon::parse($record->date)->startOfWeek(Carbon::SATURDAY)->toDateString();
            if (! isset($buckets[$weekStart])) {
                continue;
            }
            $buckets[$weekStart]['total']++;
            if (in_array($record->status, ['present', 'late'], true)) {
                $buckets[$weekStart]['present']++;
            }
        }

        return array_values($buckets);
    }
}
