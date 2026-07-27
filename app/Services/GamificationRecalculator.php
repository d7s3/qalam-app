<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Leaderboard;
use App\Models\Student;
use App\Models\StudentHadithAchievement;
use App\Models\StudentOdeAchievement;
use App\Models\StudentPlanDay;
use Illuminate\Support\Carbon;

/**
 * Replays a competition's gradings through the gamification rules.
 *
 * A gamification competition scores from its transactions, and a transaction is
 * only written when a teacher grades — against whatever criteria were enabled at
 * that moment. Turning a criterion on afterwards therefore leaves every earlier
 * grading unscored, because nothing ever revisits it. This walks the gradings
 * inside the competition's window and puts each one back through the same sync
 * the grading itself uses.
 *
 * Safe to run repeatedly: each sync looks up the transaction for its grading and
 * updates, creates or removes it, so nothing is ever counted twice.
 */
class GamificationRecalculator
{
    /**
     * @return array{quran: int, ode: int, hadith: int, attendance: int, students: int}
     */
    public static function forCompetition(Leaderboard $competition): array
    {
        $studentIds = self::studentIds($competition);

        if ($studentIds === []) {
            return ['quran' => 0, 'ode' => 0, 'hadith' => 0, 'attendance' => 0, 'students' => 0];
        }

        $from = $competition->start_date?->startOfDay();
        $to = ($competition->end_date ?? now())->endOfDay();

        $counts = [
            'quran' => self::replayQuran($studentIds, $from, $to),
            'ode' => self::replayOdes($studentIds, $from, $to),
            'hadith' => self::replayHadiths($studentIds, $from, $to),
            'attendance' => self::replayAttendance($studentIds, $from, $to),
            'students' => count($studentIds),
        ];

        // Levels, coin balances and streaks all derive from the transactions
        // above, so they only settle once those exist.
        foreach ($studentIds as $studentId) {
            GamificationService::recalculateStudentState($studentId, $competition->id);
        }

        foreach (Student::whereIn('id', $studentIds)->get() as $student) {
            GamificationService::recalculateStudentStreak($student, $competition);
        }

        return $counts;
    }

    /**
     * Every student the competition covers: its own circle, or all the circles
     * of a supervisor-run competition.
     *
     * @return array<int, int>
     */
    private static function studentIds(Leaderboard $competition): array
    {
        $circleIds = $competition->isSupervisorCompetition()
            ? $competition->circles()->pluck('circles.id')->all()
            : array_filter([$competition->circle_id]);

        if ($circleIds === []) {
            return [];
        }

        return Student::whereIn('circle_id', $circleIds)->pluck('id')->all();
    }

    /**
     * @param  array<int, int>  $studentIds
     */
    private static function replayQuran(array $studentIds, $from, $to): int
    {
        $count = 0;

        StudentPlanDay::whereHas('plan', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->where(fn ($q) => $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement'))
            ->with('plan.student')
            ->chunkById(200, function ($days) use (&$count, $from, $to) {
                foreach ($days as $day) {
                    if (! self::gradedWithin($day->hifz_graded_at ?? $day->review_graded_at ?? $day->date, $from, $to)) {
                        continue;
                    }

                    GamificationService::syncStudentPlanDayXP($day);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array<int, int>  $studentIds
     */
    private static function replayOdes(array $studentIds, $from, $to): int
    {
        $count = 0;

        StudentOdeAchievement::whereHas('plan', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->where(fn ($q) => $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement'))
            ->with('plan.student', 'pathDay')
            ->chunkById(200, function ($achievements) use (&$count, $from, $to) {
                foreach ($achievements as $achievement) {
                    $date = $achievement->hifz_graded_at ?? $achievement->review_graded_at ?? $achievement->pathDay?->date;

                    if (! self::gradedWithin($date, $from, $to)) {
                        continue;
                    }

                    GamificationService::syncStudentOdeAchievementXP($achievement);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array<int, int>  $studentIds
     */
    private static function replayHadiths(array $studentIds, $from, $to): int
    {
        $count = 0;

        StudentHadithAchievement::whereHas('plan', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->where(fn ($q) => $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement'))
            ->with('plan.student', 'pathDay')
            ->chunkById(200, function ($achievements) use (&$count, $from, $to) {
                foreach ($achievements as $achievement) {
                    $date = $achievement->hifz_graded_at ?? $achievement->review_graded_at ?? $achievement->pathDay?->date;

                    if (! self::gradedWithin($date, $from, $to)) {
                        continue;
                    }

                    GamificationService::syncStudentHadithAchievementXP($achievement);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array<int, int>  $studentIds
     */
    private static function replayAttendance(array $studentIds, $from, $to): int
    {
        $count = 0;

        Attendance::whereIn('student_id', $studentIds)
            ->whereDate('date', '>=', $from->format('Y-m-d'))
            ->whereDate('date', '<=', $to->format('Y-m-d'))
            ->chunkById(200, function ($records) use (&$count) {
                foreach ($records as $record) {
                    GamificationService::syncStudentAttendanceXP($record);
                    $count++;
                }
            });

        return $count;
    }

    private static function gradedWithin($date, $from, $to): bool
    {
        if (! $date) {
            return false;
        }

        $date = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);

        return (! $from || $date->greaterThanOrEqualTo($from)) && $date->lessThanOrEqualTo($to);
    }
}
