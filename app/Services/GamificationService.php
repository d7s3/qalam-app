<?php

namespace App\Services;

use App\Models\AcademicCalendarEvent;
use App\Models\Attendance;
use App\Models\GamificationBadge;
use App\Models\GamificationLevel;
use App\Models\GamificationStoreItem;
use App\Models\GamificationStorePurchase;
use App\Models\GamificationStudentState;
use App\Models\GamificationTeam;
use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\LeaderboardCriterion;
use App\Models\LeaderboardScore;
use App\Models\Student;
use App\Models\StudentHadithAchievement;
use App\Models\StudentOdeAchievement;
use App\Models\StudentPlanDay;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    /**
     * Get active gamification leaderboards for a student on a specific date.
     *
     * @param  string|null  $date
     * @return Collection<int, Leaderboard>
     */
    public static function getActiveLeaderboards(Student $student, $date = null)
    {
        $date = $date ? Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');

        return Leaderboard::where('is_active', true)
            ->where('competition_type', 'gamification')
            ->where('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->where(function ($query) use ($student) {
                $query->where('circle_id', $student->circle_id)
                    ->whereNull('supervisor_id')
                    ->orWhere(function ($q) use ($student) {
                        $q->whereNotNull('supervisor_id')
                            ->whereHas('circles', function ($c) use ($student) {
                                $c->where('circles.id', $student->circle_id);
                            });
                    });
            })
            ->orderByRaw('case when supervisor_id is null then 1 else 0 end')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Recalculate student state (total coins, total XP) for a leaderboard.
     */
    public static function recalculateStudentState(int $studentId, int $leaderboardId): void
    {
        $state = GamificationStudentState::firstOrNew([
            'leaderboard_id' => $leaderboardId,
            'student_id' => $studentId,
        ]);

        $coins = GamificationTransaction::where('leaderboard_id', $leaderboardId)
            ->where('student_id', $studentId)
            ->whereNull('team_id')
            ->claimed()
            ->sum('amount');

        $state->coins = max(0, (int) $coins);
        $state->save();

        // Announce a level-up in the competition news feed when XP crosses a level.
        GamificationNewsService::syncStudentLevel($studentId, $leaderboardId);
    }

    /**
     * Resolve the claimed_at value for a newly earned individual reward.
     *
     * Returns null (pending a manual claim) only when the competition has manual
     * claim enabled and this is an individual positive earning. Everything else —
     * deductions, team-treasury entries, store spends, or competitions without the
     * feature — is claimed immediately.
     */
    public static function resolveClaimedAt(Leaderboard $leaderboard, int $amount, int $xpAmount): ?CarbonInterface
    {
        $manualClaim = (bool) ($leaderboard->settings['manual_claim_enabled'] ?? false);

        if ($manualClaim && ($amount >= 0 && $xpAmount >= 0) && ($amount > 0 || $xpAmount > 0)) {
            return null;
        }

        return now();
    }

    /**
     * Sync transaction for Hifz/Review grading.
     */
    public static function syncStudentPlanDayXP(StudentPlanDay $day): void
    {
        $student = $day->plan->student;
        $date = $day->hifz_graded_at ?? $day->review_graded_at ?? $day->date;
        $leaderboards = self::getActiveLeaderboards($student, $date);

        foreach ($leaderboards as $leaderboard) {
            $settings = $leaderboard->settings ?? [];
            $xpPoints = 0;
            $coinPoints = 0;
            $details = [];

            // Hifz points
            if (($settings['hifz_enabled'] ?? false) && $day->hifz_achievement !== null) {
                $hifz = $day->hifz_achievement;
                if ($hifz === 3 || $hifz === '3' || $hifz === 'excellent') {
                    $xpPts = ($settings['hifz_excellent_xp'] ?? ($settings['hifz_excellent'] ?? 10));
                    $coinPts = ($settings['hifz_excellent_coins'] ?? ($settings['hifz_excellent'] ?? 10));
                    $xpPoints += $xpPts;
                    $coinPoints += $coinPts;
                    $details[] = "حفظ ممتاز (+{$xpPts} XP، +{$coinPts} عملة)";
                } elseif ($hifz === 2 || $hifz === '2' || $hifz === 'good') {
                    $xpPts = ($settings['hifz_good_xp'] ?? ($settings['hifz_good'] ?? 7));
                    $coinPts = ($settings['hifz_good_coins'] ?? ($settings['hifz_good'] ?? 7));
                    $xpPoints += $xpPts;
                    $coinPoints += $coinPts;
                    $details[] = "حفظ جيد (+{$xpPts} XP، +{$coinPts} عملة)";
                } elseif ($hifz === 1 || $hifz === '1' || $hifz === 'acceptable') {
                    $xpPts = ($settings['hifz_acceptable_xp'] ?? ($settings['hifz_acceptable'] ?? 4));
                    $coinPts = ($settings['hifz_acceptable_coins'] ?? ($settings['hifz_acceptable'] ?? 4));
                    $xpPoints += $xpPts;
                    $coinPoints += $coinPts;
                    $details[] = "حفظ مقبول (+{$xpPts} XP، +{$coinPts} عملة)";
                }
            }

            // Review points
            if (($settings['review_enabled'] ?? false) && $day->review_achievement !== null) {
                $review = $day->review_achievement;
                if ($review === 3 || $review === '3' || $review === 'excellent') {
                    $xpPts = ($settings['review_excellent_xp'] ?? ($settings['review_excellent'] ?? 5));
                    $coinPts = ($settings['review_excellent_coins'] ?? ($settings['review_excellent'] ?? 5));
                    $xpPoints += $xpPts;
                    $coinPoints += $coinPts;
                    $details[] = "مراجعة ممتازة (+{$xpPts} XP، +{$coinPts} عملة)";
                } elseif ($review === 2 || $review === '2' || $review === 1 || $review === 'good' || $review === 'acceptable') {
                    $xpPts = ($settings['review_good_xp'] ?? ($settings['review_good'] ?? 3));
                    $coinPts = ($settings['review_good_coins'] ?? ($settings['review_good'] ?? 3));
                    $xpPoints += $xpPts;
                    $coinPoints += $coinPts;
                    $details[] = "مراجعة جيدة (+{$xpPts} XP، +{$coinPts} عملة)";
                }
            }

            // Apply active multipliers (if any)
            $multiplier = self::getMultiplierForStudent($student, $leaderboard->id, $day->date);
            $multiplier = min(2, $multiplier);

            $finalXP = $xpPoints * $multiplier;
            $finalCoins = $coinPoints * $multiplier;

            // Find existing transaction
            $transaction = GamificationTransaction::where('leaderboard_id', $leaderboard->id)
                ->where('student_id', $student->id)
                ->where('reference_type', StudentPlanDay::class)
                ->where('reference_id', $day->id)
                ->first();

            if ($finalXP > 0 || $finalCoins > 0) {
                $desc = implode(' و ', $details).' لليوم '.$day->date->format('Y-m-d');
                if ($multiplier > 1) {
                    $desc .= ' [مضاعف النقاط نشط!]';
                }

                if ($transaction) {
                    $transaction->update([
                        'amount' => $finalCoins,
                        'xp_amount' => $finalXP,
                        'description' => $desc,
                    ]);
                } else {
                    GamificationTransaction::create([
                        'leaderboard_id' => $leaderboard->id,
                        'student_id' => $student->id,
                        'type' => 'earn',
                        'amount' => $finalCoins,
                        'xp_amount' => $finalXP,
                        'description' => $desc,
                        'reference_type' => StudentPlanDay::class,
                        'reference_id' => $day->id,
                        'claimed_at' => self::resolveClaimedAt($leaderboard, (int) $finalCoins, (int) $finalXP),
                    ]);
                }
            } else {
                if ($transaction) {
                    $transaction->delete();
                }
            }

            self::recalculateStudentState($student->id, $leaderboard->id);
            self::updateStudentStreak($student, $date, $leaderboard);
            self::syncStudentBadges($student->id, $leaderboard->id);
        }
    }

    /**
     * Sync XP/coins transaction for Ode achievement grading.
     */
    public static function syncStudentOdeAchievementXP(StudentOdeAchievement $achievement): void
    {
        $student = $achievement->plan->student;
        $date = $achievement->hifz_graded_at ?? $achievement->review_graded_at ?? $achievement->pathDay?->date;
        if (! $date) {
            return;
        }
        $leaderboards = self::getActiveLeaderboards($student, $date);

        foreach ($leaderboards as $leaderboard) {
            $settings = $leaderboard->settings ?? [];
            $xpPoints = 0;
            $coinPoints = 0;
            $details = [];

            if (($settings['ode_hifz_enabled'] ?? false) && $achievement->hifz_achievement !== null) {
                $hifz = $achievement->hifz_achievement;
                if ($hifz == 3) {
                    $xpPts = (int) ($settings['ode_hifz_excellent_xp'] ?? 10);
                    $coinPts = (int) ($settings['ode_hifz_excellent_coins'] ?? 10);
                } elseif ($hifz == 2) {
                    $xpPts = (int) ($settings['ode_hifz_good_xp'] ?? 7);
                    $coinPts = (int) ($settings['ode_hifz_good_coins'] ?? 7);
                } else {
                    $xpPts = (int) ($settings['ode_hifz_acceptable_xp'] ?? 4);
                    $coinPts = (int) ($settings['ode_hifz_acceptable_coins'] ?? 4);
                }
                $xpPoints += $xpPts;
                $coinPoints += $coinPts;
                $details[] = "حفظ منظومة (+{$xpPts} XP، +{$coinPts} عملة)";
            }

            if (($settings['ode_review_enabled'] ?? false) && $achievement->review_achievement !== null) {
                $rev = $achievement->review_achievement;
                if ($rev == 3) {
                    $xpPts = (int) ($settings['ode_review_excellent_xp'] ?? 5);
                    $coinPts = (int) ($settings['ode_review_excellent_coins'] ?? 5);
                } else {
                    $xpPts = (int) ($settings['ode_review_good_xp'] ?? 3);
                    $coinPts = (int) ($settings['ode_review_good_coins'] ?? 3);
                }
                $xpPoints += $xpPts;
                $coinPoints += $coinPts;
                $details[] = "مراجعة منظومة (+{$xpPts} XP، +{$coinPts} عملة)";
            }

            $multiplier = min(2, self::getMultiplierForStudent($student, $leaderboard->id, $date));
            $finalXP = $xpPoints * $multiplier;
            $finalCoins = $coinPoints * $multiplier;

            $transaction = GamificationTransaction::where('leaderboard_id', $leaderboard->id)
                ->where('student_id', $student->id)
                ->where('reference_type', StudentOdeAchievement::class)
                ->where('reference_id', $achievement->id)
                ->first();

            if ($finalXP > 0 || $finalCoins > 0) {
                $dateStr = ($date instanceof Carbon ? $date : Carbon::parse($date))->format('Y-m-d');
                $desc = implode(' و ', $details).' لليوم '.$dateStr;
                if ($multiplier > 1) {
                    $desc .= ' [مضاعف النقاط نشط!]';
                }
                if ($transaction) {
                    $transaction->update(['amount' => $finalCoins, 'xp_amount' => $finalXP, 'description' => $desc]);
                } else {
                    GamificationTransaction::create([
                        'leaderboard_id' => $leaderboard->id,
                        'student_id' => $student->id,
                        'type' => 'earn',
                        'amount' => $finalCoins,
                        'xp_amount' => $finalXP,
                        'description' => $desc,
                        'reference_type' => StudentOdeAchievement::class,
                        'reference_id' => $achievement->id,
                        'claimed_at' => self::resolveClaimedAt($leaderboard, (int) $finalCoins, (int) $finalXP),
                    ]);
                }
            } elseif ($transaction) {
                $transaction->delete();
            }

            self::recalculateStudentState($student->id, $leaderboard->id);
            self::updateStudentStreak($student, $date, $leaderboard);
            self::syncStudentBadges($student->id, $leaderboard->id);
        }
    }

    /**
     * Sync XP/coins transaction for Hadith achievement grading.
     */
    public static function syncStudentHadithAchievementXP(StudentHadithAchievement $achievement): void
    {
        $student = $achievement->plan->student;
        $date = $achievement->hifz_graded_at ?? $achievement->review_graded_at ?? $achievement->pathDay?->date;
        if (! $date) {
            return;
        }
        $leaderboards = self::getActiveLeaderboards($student, $date);

        foreach ($leaderboards as $leaderboard) {
            $settings = $leaderboard->settings ?? [];
            $xpPoints = 0;
            $coinPoints = 0;
            $details = [];

            if (($settings['hadith_hifz_enabled'] ?? false) && $achievement->hifz_achievement !== null) {
                $hifz = $achievement->hifz_achievement;
                if ($hifz == 3) {
                    $xpPts = (int) ($settings['hadith_hifz_excellent_xp'] ?? 10);
                    $coinPts = (int) ($settings['hadith_hifz_excellent_coins'] ?? 10);
                } elseif ($hifz == 2) {
                    $xpPts = (int) ($settings['hadith_hifz_good_xp'] ?? 7);
                    $coinPts = (int) ($settings['hadith_hifz_good_coins'] ?? 7);
                } else {
                    $xpPts = (int) ($settings['hadith_hifz_acceptable_xp'] ?? 4);
                    $coinPts = (int) ($settings['hadith_hifz_acceptable_coins'] ?? 4);
                }
                $xpPoints += $xpPts;
                $coinPoints += $coinPts;
                $details[] = "حفظ حديث (+{$xpPts} XP، +{$coinPts} عملة)";
            }

            if (($settings['hadith_review_enabled'] ?? false) && $achievement->review_achievement !== null) {
                $rev = $achievement->review_achievement;
                if ($rev == 3) {
                    $xpPts = (int) ($settings['hadith_review_excellent_xp'] ?? 5);
                    $coinPts = (int) ($settings['hadith_review_excellent_coins'] ?? 5);
                } else {
                    $xpPts = (int) ($settings['hadith_review_good_xp'] ?? 3);
                    $coinPts = (int) ($settings['hadith_review_good_coins'] ?? 3);
                }
                $xpPoints += $xpPts;
                $coinPoints += $coinPts;
                $details[] = "مراجعة حديث (+{$xpPts} XP، +{$coinPts} عملة)";
            }

            $multiplier = min(2, self::getMultiplierForStudent($student, $leaderboard->id, $date));
            $finalXP = $xpPoints * $multiplier;
            $finalCoins = $coinPoints * $multiplier;

            $transaction = GamificationTransaction::where('leaderboard_id', $leaderboard->id)
                ->where('student_id', $student->id)
                ->where('reference_type', StudentHadithAchievement::class)
                ->where('reference_id', $achievement->id)
                ->first();

            if ($finalXP > 0 || $finalCoins > 0) {
                $dateStr = ($date instanceof Carbon ? $date : Carbon::parse($date))->format('Y-m-d');
                $desc = implode(' و ', $details).' لليوم '.$dateStr;
                if ($multiplier > 1) {
                    $desc .= ' [مضاعف النقاط نشط!]';
                }
                if ($transaction) {
                    $transaction->update(['amount' => $finalCoins, 'xp_amount' => $finalXP, 'description' => $desc]);
                } else {
                    GamificationTransaction::create([
                        'leaderboard_id' => $leaderboard->id,
                        'student_id' => $student->id,
                        'type' => 'earn',
                        'amount' => $finalCoins,
                        'xp_amount' => $finalXP,
                        'description' => $desc,
                        'reference_type' => StudentHadithAchievement::class,
                        'reference_id' => $achievement->id,
                        'claimed_at' => self::resolveClaimedAt($leaderboard, (int) $finalCoins, (int) $finalXP),
                    ]);
                }
            } elseif ($transaction) {
                $transaction->delete();
            }

            self::recalculateStudentState($student->id, $leaderboard->id);
            self::updateStudentStreak($student, $date, $leaderboard);
            self::syncStudentBadges($student->id, $leaderboard->id);
        }
    }

    /**
     * Sync transaction for Attendance.
     */
    public static function syncStudentAttendanceXP(Attendance $attendance): void
    {
        $student = $attendance->student;
        $leaderboards = self::getActiveLeaderboards($student, $attendance->date);

        foreach ($leaderboards as $leaderboard) {
            $settings = $leaderboard->settings ?? [];
            $xpPoints = 0;
            $coinPoints = 0;
            $desc = '';

            if ($settings['attendance_enabled'] ?? false) {
                if ($attendance->status === 'present') {
                    $xpPts = ($settings['attendance_present_xp'] ?? ($settings['attendance_present'] ?? 4));
                    $coinPts = ($settings['attendance_present_coins'] ?? ($settings['attendance_present'] ?? 4));
                    $xpPoints = $xpPts;
                    $coinPoints = $coinPts;
                    $desc = "حضور الحلقة (+{$xpPts} XP، +{$coinPts} عملة)";
                } elseif ($attendance->status === 'late') {
                    $xpPts = ($settings['attendance_late_xp'] ?? ($settings['attendance_late'] ?? 2));
                    $coinPts = ($settings['attendance_late_coins'] ?? ($settings['attendance_late'] ?? 2));
                    $xpPoints = $xpPts;
                    $coinPoints = $coinPts;
                    $desc = "حضور الحلقة متأخراً (+{$xpPts} XP، +{$coinPts} عملة)";
                }
            }

            // Apply active multipliers (if any)
            $multiplier = self::getMultiplierForStudent($student, $leaderboard->id, $attendance->date);
            $multiplier = min(2, $multiplier);

            $finalXP = $xpPoints * $multiplier;
            $finalCoins = $coinPoints * $multiplier;

            $transaction = GamificationTransaction::where('leaderboard_id', $leaderboard->id)
                ->where('student_id', $student->id)
                ->where('reference_type', Attendance::class)
                ->where('reference_id', $attendance->id)
                ->first();

            if ($finalXP > 0 || $finalCoins > 0) {
                if ($multiplier > 1) {
                    $desc .= ' [مضاعف النقاط نشط!]';
                }

                if ($transaction) {
                    $transaction->update([
                        'amount' => $finalCoins,
                        'xp_amount' => $finalXP,
                        'description' => $desc,
                    ]);
                } else {
                    GamificationTransaction::create([
                        'leaderboard_id' => $leaderboard->id,
                        'student_id' => $student->id,
                        'type' => 'earn',
                        'amount' => $finalCoins,
                        'xp_amount' => $finalXP,
                        'description' => $desc,
                        'reference_type' => Attendance::class,
                        'reference_id' => $attendance->id,
                        'claimed_at' => self::resolveClaimedAt($leaderboard, (int) $finalCoins, (int) $finalXP),
                    ]);
                }
            } else {
                if ($transaction) {
                    $transaction->delete();
                }
            }

            self::recalculateStudentState($student->id, $leaderboard->id);
            self::updateStudentStreak($student, $attendance->date, $leaderboard);
            self::syncStudentBadges($student->id, $leaderboard->id);
        }
    }

    /**
     * Sync transaction for custom criteria.
     */
    public static function syncStudentCustomCriterionXP(LeaderboardScore $score): void
    {
        $student = $score->student;
        $leaderboard = $score->leaderboard;

        if ($leaderboard->competition_type !== 'gamification') {
            return;
        }

        $criterion = $score->criterion;
        $xpPoints = (int) $criterion->points;
        $coinPoints = (int) (($criterion->coins ?? 0) > 0 ? $criterion->coins : $criterion->points);

        // Apply active multipliers (if any)
        $multiplier = self::getMultiplierForStudent($student, $leaderboard->id, $score->date);
        $multiplier = min(2, $multiplier);

        $finalXP = $xpPoints * $multiplier;
        $finalCoins = $coinPoints * $multiplier;

        $transaction = GamificationTransaction::where('leaderboard_id', $leaderboard->id)
            ->where('student_id', $student->id)
            ->where('reference_type', LeaderboardScore::class)
            ->where('reference_id', $score->id)
            ->first();

        if ($finalXP > 0 || $finalCoins > 0) {
            $desc = "تقييم بند مخصص: {$criterion->name} (+{$finalXP} XP، +{$finalCoins} عملة)";
            if ($multiplier > 1) {
                $desc .= ' [مضاعف النقاط نشط!]';
            }

            if ($transaction) {
                $transaction->update([
                    'amount' => $finalCoins,
                    'xp_amount' => $finalXP,
                    'description' => $desc,
                ]);
            } else {
                GamificationTransaction::create([
                    'leaderboard_id' => $leaderboard->id,
                    'student_id' => $student->id,
                    'type' => 'earn',
                    'amount' => $finalCoins,
                    'xp_amount' => $finalXP,
                    'description' => $desc,
                    'reference_type' => LeaderboardScore::class,
                    'reference_id' => $score->id,
                    'claimed_at' => self::resolveClaimedAt($leaderboard, (int) $finalCoins, (int) $finalXP),
                ]);
            }
        } else {
            if ($transaction) {
                $transaction->delete();
            }
        }

        self::recalculateStudentState($student->id, $leaderboard->id);
        self::updateStudentStreak($student, $score->date, $leaderboard);
        self::syncStudentBadges($student->id, $leaderboard->id);
    }

    /**
     * Sync transaction for extra points.
     */
    public static function syncStudentExtraPointsXP(int $extraPointId): void
    {
        $extraPoint = DB::table('leaderboard_extra_points')->where('id', $extraPointId)->first();
        if (! $extraPoint) {
            // If deleted, delete corresponding transaction
            $transaction = GamificationTransaction::where('reference_type', 'leaderboard_extra_points')
                ->where('reference_id', $extraPointId)
                ->first();

            if ($transaction) {
                $studentId = $transaction->student_id;
                $leaderboardId = $transaction->leaderboard_id;
                $transaction->delete();
                self::recalculateStudentState($studentId, $leaderboardId);
            }

            return;
        }

        $leaderboard = Leaderboard::find($extraPoint->leaderboard_id);
        if (! $leaderboard || $leaderboard->competition_type !== 'gamification') {
            return;
        }

        $student = Student::find($extraPoint->student_id);
        if (! $student) {
            return;
        }

        $points = (int) $extraPoint->points;

        $transaction = GamificationTransaction::where('leaderboard_id', $leaderboard->id)
            ->where('student_id', $student->id)
            ->where('reference_type', 'leaderboard_extra_points')
            ->where('reference_id', $extraPointId)
            ->first();

        if ($points !== 0) {
            $desc = 'نقاط إضافية من المعلم: '.($extraPoint->notes ?: 'بدون ملاحظات')." ($points)";

            // Always an "earn" transaction: a negative value is a teacher deduction that
            // must reduce both the student's coins and their XP/standing (not coins only).
            if ($transaction) {
                $transaction->update([
                    'amount' => $points,
                    'xp_amount' => $points,
                    'type' => 'earn',
                    'description' => $desc,
                ]);
            } else {
                GamificationTransaction::create([
                    'leaderboard_id' => $leaderboard->id,
                    'student_id' => $student->id,
                    'type' => 'earn',
                    'amount' => $points,
                    'xp_amount' => $points,
                    'description' => $desc,
                    'reference_type' => 'leaderboard_extra_points',
                    'reference_id' => $extraPointId,
                    'claimed_at' => self::resolveClaimedAt($leaderboard, (int) $points, (int) $points),
                ]);
            }
        } else {
            if ($transaction) {
                $transaction->delete();
            }
        }

        self::recalculateStudentState($student->id, $leaderboard->id);
    }

    /**
     * Streak calculation engine.
     *
     * @param  mixed  $date
     */
    /**
     * Check if the student achieved the enthusiasm criteria on a specific date.
     */
    public static function checkEnthusiasmForDate(Student $student, string $dateStr, Leaderboard $leaderboard): bool
    {
        $settings = $leaderboard->settings ?? [];
        if (! ($settings['enthusiasm_enabled'] ?? false)) {
            return false;
        }

        $hifzTrigger = (bool) ($settings['hifz_enthusiasm_trigger'] ?? true);
        $reviewTrigger = (bool) ($settings['review_enthusiasm_trigger'] ?? true);
        $attendanceTrigger = (bool) ($settings['attendance_enthusiasm_trigger'] ?? true);
        $odeHifzTrigger = (bool) ($settings['ode_hifz_enthusiasm_trigger'] ?? false);
        $odeReviewTrigger = (bool) ($settings['ode_review_enthusiasm_trigger'] ?? false);
        $hadithHifzTrigger = (bool) ($settings['hadith_hifz_enthusiasm_trigger'] ?? false);
        $hadithReviewTrigger = (bool) ($settings['hadith_review_enthusiasm_trigger'] ?? false);

        // 1. Attendance Trigger
        $attendanceMet = false;
        if ($attendanceTrigger) {
            $attendance = Attendance::where('student_id', $student->id)
                ->whereDate('date', $dateStr)
                ->first();
            if ($attendance && in_array($attendance->status, ['present', 'late'])) {
                $attendanceMet = true;
            }
        }

        // 2. Achievements Trigger (Hifz / Review)
        $achievementMet = false;
        if ($hifzTrigger || $reviewTrigger) {
            $dayPlan = StudentPlanDay::whereHas('plan', function ($q) use ($student) {
                $q->where('student_id', $student->id)->where('is_approved', 1);
            })
                ->where(function ($q) use ($dateStr) {
                    $q->whereDate('hifz_graded_at', $dateStr)
                        ->orWhereDate('review_graded_at', $dateStr)
                        ->orWhere(function ($sub) use ($dateStr) {
                            $sub->whereNull('hifz_graded_at')
                                ->whereNull('review_graded_at')
                                ->whereDate('date', $dateStr)
                                ->where(function ($s) {
                                    $s->whereNotNull('hifz_achievement')
                                        ->orWhereNotNull('review_achievement');
                                });
                        });
                })
                ->get();

            if ($dayPlan->isNotEmpty()) {
                foreach ($dayPlan as $dp) {
                    $hifzAch = $dp->hifz_achievement;
                    $revAch = $dp->review_achievement;

                    $hifzOk = $hifzTrigger && $hifzAch !== null && in_array($hifzAch, [1, 2, 3, '1', '2', '3', 'excellent', 'good', 'acceptable']);
                    $revOk = $reviewTrigger && $revAch !== null && in_array($revAch, [1, 2, 3, '1', '2', '3', 'excellent', 'good', 'acceptable']);

                    if ($hifzOk || $revOk) {
                        $achievementMet = true;
                        break;
                    }
                }
            }
        }

        // 2b. Ode & Hadith Achievement Trigger
        if (! $achievementMet && ($odeHifzTrigger || $odeReviewTrigger)) {
            $odeAch = StudentOdeAchievement::whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
                ->where(function ($q) use ($dateStr) {
                    $q->whereDate('hifz_graded_at', $dateStr)
                        ->orWhereDate('review_graded_at', $dateStr)
                        ->orWhere(fn ($sub) => $sub->whereNull('hifz_graded_at')->whereNull('review_graded_at')
                            ->whereHas('pathDay', fn ($pd) => $pd->whereDate('date', $dateStr))
                            ->where(fn ($s) => $s->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement')));
                })->first();
            if ($odeAch) {
                $hifzOk = $odeHifzTrigger && $odeAch->hifz_achievement !== null;
                $revOk = $odeReviewTrigger && $odeAch->review_achievement !== null;
                if ($hifzOk || $revOk) {
                    $achievementMet = true;
                }
            }
        }

        if (! $achievementMet && ($hadithHifzTrigger || $hadithReviewTrigger)) {
            $hadithAch = StudentHadithAchievement::whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
                ->where(function ($q) use ($dateStr) {
                    $q->whereDate('hifz_graded_at', $dateStr)
                        ->orWhereDate('review_graded_at', $dateStr)
                        ->orWhere(fn ($sub) => $sub->whereNull('hifz_graded_at')->whereNull('review_graded_at')
                            ->whereHas('pathDay', fn ($pd) => $pd->whereDate('date', $dateStr))
                            ->where(fn ($s) => $s->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement')));
                })->first();
            if ($hadithAch) {
                $hifzOk = $hadithHifzTrigger && $hadithAch->hifz_achievement !== null;
                $revOk = $hadithReviewTrigger && $hadithAch->review_achievement !== null;
                if ($hifzOk || $revOk) {
                    $achievementMet = true;
                }
            }
        }

        // 3. Custom Criteria Trigger
        $customMet = false;
        $customCriteriaIds = LeaderboardCriterion::where('leaderboard_id', $leaderboard->id)
            ->where('is_enthusiasm_trigger', true)
            ->pluck('id')
            ->toArray();

        if (! empty($customCriteriaIds)) {
            $hasCustomScore = LeaderboardScore::where('leaderboard_id', $leaderboard->id)
                ->where('student_id', $student->id)
                ->whereIn('leaderboard_criterion_id', $customCriteriaIds)
                ->whereDate('date', $dateStr)
                ->exists();
            if ($hasCustomScore) {
                $customMet = true;
            }
        }

        return $attendanceMet || $achievementMet || $customMet;
    }

    /**
     * Get enthusiasm details for all dates in a range for a student in a leaderboard (Batch optimized).
     *
     * @return array<string, bool> Map of date string to enthusiasm status (true/false)
     */
    public static function getEnthusiasmMapForRange(Student $student, string $startDate, string $endDate, Leaderboard $leaderboard): array
    {
        $settings = $leaderboard->settings ?? [];
        if (! ($settings['enthusiasm_enabled'] ?? false)) {
            return [];
        }

        $startDate = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($endDate)->endOfDay()->toDateTimeString();

        $hifzTrigger = (bool) ($settings['hifz_enthusiasm_trigger'] ?? true);
        $reviewTrigger = (bool) ($settings['review_enthusiasm_trigger'] ?? true);
        $attendanceTrigger = (bool) ($settings['attendance_enthusiasm_trigger'] ?? true);
        $odeHifzTrigger = (bool) ($settings['ode_hifz_enthusiasm_trigger'] ?? false);
        $odeReviewTrigger = (bool) ($settings['ode_review_enthusiasm_trigger'] ?? false);
        $hadithHifzTrigger = (bool) ($settings['hadith_hifz_enthusiasm_trigger'] ?? false);
        $hadithReviewTrigger = (bool) ($settings['hadith_review_enthusiasm_trigger'] ?? false);

        // Fetch attendances in range
        $attendances = Attendance::where('student_id', $student->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn ($a) => Carbon::parse($a->date)->format('Y-m-d'));

        // Fetch plan days in range
        $planDays = StudentPlanDay::whereHas('plan', function ($q) use ($student) {
            $q->where('student_id', $student->id)->where('is_approved', 1);
        })
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate])
                    ->orWhereBetween('hifz_graded_at', [$startDate, $endDate])
                    ->orWhereBetween('review_graded_at', [$startDate, $endDate]);
            })
            ->get();

        $evalPlanDays = [];
        foreach ($planDays as $dp) {
            $hifzDate = $dp->hifz_graded_at ? Carbon::parse($dp->hifz_graded_at)->format('Y-m-d') : null;
            $revDate = $dp->review_graded_at ? Carbon::parse($dp->review_graded_at)->format('Y-m-d') : null;
            $schedDate = Carbon::parse($dp->date)->format('Y-m-d');

            if ($dp->hifz_achievement !== null) {
                $date = $hifzDate ?: $schedDate;
                $evalPlanDays[$date][] = ['type' => 'hifz', 'achievement' => $dp->hifz_achievement];
            }
            if ($dp->review_achievement !== null) {
                $date = $revDate ?: $schedDate;
                $evalPlanDays[$date][] = ['type' => 'review', 'achievement' => $dp->review_achievement];
            }
        }

        // Fetch ode achievements in range
        $evalOdeDays = [];
        if ($odeHifzTrigger || $odeReviewTrigger) {
            $odeAchs = StudentOdeAchievement::whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('hifz_graded_at', [$startDate, $endDate])
                        ->orWhereBetween('review_graded_at', [$startDate, $endDate])
                        ->orWhereHas('pathDay', fn ($pd) => $pd->whereBetween('date', [$startDate, $endDate]));
                })->with('pathDay')->get();

            foreach ($odeAchs as $oa) {
                $schedDate = $oa->pathDay ? Carbon::parse($oa->pathDay->date)->format('Y-m-d') : null;
                if ($oa->hifz_achievement !== null) {
                    $date = $oa->hifz_graded_at ? Carbon::parse($oa->hifz_graded_at)->format('Y-m-d') : $schedDate;
                    if ($date) {
                        $evalOdeDays[$date][] = ['type' => 'hifz', 'achievement' => $oa->hifz_achievement];
                    }
                }
                if ($oa->review_achievement !== null) {
                    $date = $oa->review_graded_at ? Carbon::parse($oa->review_graded_at)->format('Y-m-d') : $schedDate;
                    if ($date) {
                        $evalOdeDays[$date][] = ['type' => 'review', 'achievement' => $oa->review_achievement];
                    }
                }
            }
        }

        // Fetch hadith achievements in range
        $evalHadithDays = [];
        if ($hadithHifzTrigger || $hadithReviewTrigger) {
            $hadithAchs = StudentHadithAchievement::whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('hifz_graded_at', [$startDate, $endDate])
                        ->orWhereBetween('review_graded_at', [$startDate, $endDate])
                        ->orWhereHas('pathDay', fn ($pd) => $pd->whereBetween('date', [$startDate, $endDate]));
                })->with('pathDay')->get();

            foreach ($hadithAchs as $ha) {
                $schedDate = $ha->pathDay ? Carbon::parse($ha->pathDay->date)->format('Y-m-d') : null;
                if ($ha->hifz_achievement !== null) {
                    $date = $ha->hifz_graded_at ? Carbon::parse($ha->hifz_graded_at)->format('Y-m-d') : $schedDate;
                    if ($date) {
                        $evalHadithDays[$date][] = ['type' => 'hifz', 'achievement' => $ha->hifz_achievement];
                    }
                }
                if ($ha->review_achievement !== null) {
                    $date = $ha->review_graded_at ? Carbon::parse($ha->review_graded_at)->format('Y-m-d') : $schedDate;
                    if ($date) {
                        $evalHadithDays[$date][] = ['type' => 'review', 'achievement' => $ha->review_achievement];
                    }
                }
            }
        }

        // Fetch custom scores in range
        $customCriteriaIds = LeaderboardCriterion::where('leaderboard_id', $leaderboard->id)
            ->where('is_enthusiasm_trigger', true)
            ->pluck('id')
            ->toArray();

        $customScoreDates = [];
        if (! empty($customCriteriaIds)) {
            $customScoreDates = LeaderboardScore::where('leaderboard_id', $leaderboard->id)
                ->where('student_id', $student->id)
                ->whereIn('leaderboard_criterion_id', $customCriteriaIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
                ->toArray();
        }

        // Loop through each day in the range and evaluate from memory
        $map = [];
        $current = Carbon::parse($startDate)->copy();
        $end = Carbon::parse($endDate);

        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');

            // 1. Attendance Met
            $attendanceMet = false;
            if ($attendanceTrigger) {
                $atts = $attendances->get($dateStr);
                if ($atts) {
                    foreach ($atts as $att) {
                        if (in_array($att->status, ['present', 'late'])) {
                            $attendanceMet = true;
                            break;
                        }
                    }
                }
            }

            // 2. Achievements Met
            $achievementMet = false;
            if ($hifzTrigger || $reviewTrigger) {
                $dayEvals = $evalPlanDays[$dateStr] ?? [];
                if (! empty($dayEvals)) {
                    foreach ($dayEvals as $ev) {
                        $ach = $ev['achievement'];
                        $type = $ev['type'];

                        if ($type === 'hifz' && $hifzTrigger) {
                            $ok = $ach !== null && in_array($ach, [1, 2, 3, '1', '2', '3', 'excellent', 'good', 'acceptable']);
                        } elseif ($type === 'review' && $reviewTrigger) {
                            $ok = $ach !== null && in_array($ach, [1, 2, 3, '1', '2', '3', 'excellent', 'good', 'acceptable']);
                        } else {
                            $ok = false;
                        }

                        if ($ok) {
                            $achievementMet = true;
                            break;
                        }
                    }
                }
            }

            if (! $achievementMet && ($odeHifzTrigger || $odeReviewTrigger)) {
                foreach ($evalOdeDays[$dateStr] ?? [] as $ev) {
                    $ok = ($ev['type'] === 'hifz' && $odeHifzTrigger && $ev['achievement'] !== null)
                        || ($ev['type'] === 'review' && $odeReviewTrigger && $ev['achievement'] !== null);
                    if ($ok) {
                        $achievementMet = true;
                        break;
                    }
                }
            }

            if (! $achievementMet && ($hadithHifzTrigger || $hadithReviewTrigger)) {
                foreach ($evalHadithDays[$dateStr] ?? [] as $ev) {
                    $ok = ($ev['type'] === 'hifz' && $hadithHifzTrigger && $ev['achievement'] !== null)
                        || ($ev['type'] === 'review' && $hadithReviewTrigger && $ev['achievement'] !== null);
                    if ($ok) {
                        $achievementMet = true;
                        break;
                    }
                }
            }

            // 3. Custom Met
            $customMet = in_array($dateStr, $customScoreDates);

            // Evaluate
            $triggered = $attendanceMet || $achievementMet || $customMet;

            $map[$dateStr] = $triggered;

            $current->addDay();
        }

        return $map;
    }

    /**
     * Streak calculation engine.
     *
     * @param  mixed  $date
     */
    public static function updateStudentStreak(Student $student, $date, Leaderboard $leaderboard): void
    {
        self::recalculateStudentStreak($student, $leaderboard);
    }

    /**
     * Get all working days for a leaderboard.
     *
     * @return array<string> List of date strings (Y-m-d)
     */
    public static function getWorkingDaysForLeaderboard(Leaderboard $leaderboard): array
    {
        $startDateStr = Carbon::parse($leaderboard->start_date)->format('Y-m-d');
        $endDateStr = Carbon::parse($leaderboard->end_date)->format('Y-m-d');

        $calendarEvents = AcademicCalendarEvent::where('is_attendance_period', true)
            ->where('start_date', '<=', $endDateStr)
            ->where(function ($q) use ($startDateStr) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startDateStr);
            })
            ->get();

        $hasCalendarEvents = $calendarEvents->isNotEmpty();

        $currentDate = Carbon::parse($startDateStr)->copy();
        $endCarbon = Carbon::parse($endDateStr);
        $workingDays = [];

        while ($currentDate->lte($endCarbon)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek + 1; // 1=Sun, 2=Mon, 3=Tue, 4=Wed, 5=Thu, 6=Fri, 7=Sat

            $isWorking = false;
            if ($hasCalendarEvents) {
                $isWorking = $calendarEvents->some(function ($event) use ($dateStr, $dayOfWeek) {
                    $eventStart = Carbon::parse($event->start_date)->format('Y-m-d');
                    $eventEnd = $event->end_date ? Carbon::parse($event->end_date)->format('Y-m-d') : null;

                    if ($dateStr < $eventStart || ($eventEnd && $dateStr > $eventEnd)) {
                        return false;
                    }

                    $weekdays = $event->weekdays ?? [];

                    return empty($weekdays) || in_array($dayOfWeek, $weekdays);
                });
            } else {
                $isWorking = in_array($dayOfWeek, [1, 2, 3, 4, 5]);
            }

            if ($isWorking) {
                $workingDays[] = $dateStr;
            }

            $currentDate->addDay();
        }

        return $workingDays;
    }

    /**
     * Recalculate student streak and milestones chronologically.
     */
    public static function recalculateStudentStreak(Student $student, Leaderboard $leaderboard): void
    {
        $settings = $leaderboard->settings ?? [];
        if (! ($settings['enthusiasm_enabled'] ?? false)) {
            return;
        }

        $startDateStr = Carbon::parse($leaderboard->start_date)->format('Y-m-d');
        $endDateStr = Carbon::parse($leaderboard->end_date)->format('Y-m-d');

        // Fetch enthusiasm map with full time bounds
        $enthusiasmMap = self::getEnthusiasmMapForRange($student, $startDateStr, $endDateStr, $leaderboard);

        // Get working days for the leaderboard
        $workingDaysDates = self::getWorkingDaysForLeaderboard($leaderboard);

        // Get all approved purchases of streak freezes with target_date
        $freezesPurchasedDates = GamificationStorePurchase::where('student_id', $student->id)
            ->where('status', 'approved')
            ->whereHas('item', fn ($q) => $q->where('is_streak_freeze', true))
            ->whereNotNull('target_date')
            ->pluck('target_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // 1. Get previously claimed milestone IDs that are already 'claimed'
        $previouslyClaimedMilestoneIds = DB::table('gamification_claimed_milestones')
            ->where('student_id', $student->id)
            ->where('status', 'claimed')
            ->pluck('milestone_id')
            ->toArray();

        // 2. Delete all claimed milestone records for this student on this leaderboard
        $milestoneIds = DB::table('gamification_streak_milestones')
            ->where('leaderboard_id', $leaderboard->id)
            ->pluck('id')
            ->toArray();

        DB::table('gamification_claimed_milestones')
            ->where('student_id', $student->id)
            ->whereIn('milestone_id', $milestoneIds)
            ->delete();

        // 3. Delete all milestone transactions for this student on this leaderboard
        GamificationTransaction::where('leaderboard_id', $leaderboard->id)
            ->where('student_id', $student->id)
            ->where('type', 'earn')
            ->where('description', 'like', 'مكافأة أيام الحماسة لـ%')
            ->delete();

        // 4. Delete all freeze consumption transactions to recreate them (deprecated, but keep cleanup for old logs)
        GamificationTransaction::where('leaderboard_id', $leaderboard->id)
            ->where('student_id', $student->id)
            ->where('type', 'spend')
            ->where('description', 'like', 'استهلاك عدد (%')
            ->delete();

        // Simulation state
        $currentStreak = 0;
        $maxStreak = 0;
        $lastActivityDate = null;
        $claimedInCurrentRun = [];

        $milestones = DB::table('gamification_streak_milestones')
            ->where('leaderboard_id', $leaderboard->id)
            ->orderBy('days_required', 'asc')
            ->get();

        foreach ($workingDaysDates as $dateStr) {
            $triggered = $enthusiasmMap[$dateStr] ?? false;
            $isFrozen = in_array($dateStr, $freezesPurchasedDates);

            if ($triggered || $isFrozen) {
                if ($lastActivityDate === null) {
                    $currentStreak = 1;
                    $lastActivityDate = $dateStr;
                    $claimedInCurrentRun = [];
                } else {
                    $currIdx = array_search($dateStr, $workingDaysDates);
                    $lastIdx = array_search($lastActivityDate, $workingDaysDates);
                    $diffInWorkingDays = ($currIdx !== false && $lastIdx !== false) ? ($currIdx - $lastIdx) : 1;

                    if ($diffInWorkingDays === 1) {
                        $currentStreak++;
                        $lastActivityDate = $dateStr;
                    } else {
                        // Streak broken
                        $currentStreak = 1;
                        $lastActivityDate = $dateStr;
                        $claimedInCurrentRun = [];
                    }
                }

                if ($currentStreak > $maxStreak) {
                    $maxStreak = $currentStreak;
                }

                // Check and award milestones for the current streak on this day
                foreach ($milestones as $milestone) {
                    if ($milestone->days_required <= $currentStreak && ! in_array($milestone->id, $claimedInCurrentRun)) {
                        $isPreviouslyClaimed = in_array($milestone->id, $previouslyClaimedMilestoneIds);

                        DB::table('gamification_claimed_milestones')->insert([
                            'student_id' => $student->id,
                            'milestone_id' => $milestone->id,
                            'streak_run_count' => $currentStreak,
                            'status' => $isPreviouslyClaimed ? 'claimed' : 'approved',
                            'created_at' => Carbon::parse($dateStr)->toDateTimeString(),
                            'updated_at' => Carbon::parse($dateStr)->toDateTimeString(),
                        ]);

                        if ($isPreviouslyClaimed) {
                            if ($milestone->reward_xp > 0 || $milestone->reward_coins > 0) {
                                GamificationTransaction::create([
                                    'leaderboard_id' => $leaderboard->id,
                                    'student_id' => $student->id,
                                    'type' => 'earn',
                                    'amount' => $milestone->reward_coins,
                                    'xp_amount' => $milestone->reward_xp,
                                    'description' => "مكافأة أيام الحماسة لـ {$milestone->days_required} أيام متتالية: {$milestone->description}",
                                    'created_at' => Carbon::parse($dateStr)->toDateTimeString(),
                                    'updated_at' => Carbon::parse($dateStr)->toDateTimeString(),
                                ]);
                            }
                        }

                        if ($milestone->reward_badge_id) {
                            $hasBadge = DB::table('gamification_badge_student')
                                ->where('badge_id', $milestone->reward_badge_id)
                                ->where('student_id', $student->id)
                                ->exists();

                            if (! $hasBadge) {
                                DB::table('gamification_badge_student')->insert([
                                    'badge_id' => $milestone->reward_badge_id,
                                    'student_id' => $student->id,
                                    'status' => 'pending_approval',
                                    'created_at' => Carbon::parse($dateStr)->toDateTimeString(),
                                    'updated_at' => Carbon::parse($dateStr)->toDateTimeString(),
                                ]);
                            }
                        }

                        $claimedInCurrentRun[] = $milestone->id;
                    }
                }
            }
        }

        // Save simulated state
        $state = GamificationStudentState::firstOrNew([
            'leaderboard_id' => $leaderboard->id,
            'student_id' => $student->id,
        ]);

        $state->current_streak = $currentStreak;
        $state->max_streak = $maxStreak;
        $state->last_activity_date = $lastActivityDate ? Carbon::parse($lastActivityDate) : null;
        $state->streak_freezes_count = count($freezesPurchasedDates);
        $state->save();

        self::recalculateStudentState($student->id, $leaderboard->id);
    }

    /**
     * Resolve the student's daily team-donation allowance.
     *
     * The daily limit is a percentage (configured per level) of the student's coin
     * balance at the START of the current day. Coins earned or spent during the same
     * day do not change this base, so the allowance is stable throughout the day.
     *
     * @return array{has_donation: bool, percentage: int, base: int, limit: int, donated: int, remaining: int}
     */
    public static function getDailyDonationStatus(int $studentId, int $leaderboardId): array
    {
        // Read the donation rules from the student's ACTUAL current level
        // (getStudentLevel returns the resolved level object under 'current').
        $currentLevel = self::getStudentLevel($studentId, $leaderboardId)['current'] ?? null;
        $levelSettings = $currentLevel->settings ?? [];
        $hasDonation = (bool) ($levelSettings['has_donation'] ?? true);
        $percentage = (int) ($levelSettings['donation_max_limit'] ?? 10);

        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        // Coin balance as it stood at the start of today (all claimed transactions before today).
        $base = (int) GamificationTransaction::where('student_id', $studentId)
            ->where('leaderboard_id', $leaderboardId)
            ->whereNull('team_id')
            ->claimed()
            ->where('created_at', '<', $todayStart)
            ->sum('amount');
        $base = max(0, $base);

        $limit = (int) floor(($percentage / 100) * $base);

        $donated = (int) abs(GamificationTransaction::where('student_id', $studentId)
            ->where('leaderboard_id', $leaderboardId)
            ->where('type', 'spend')
            ->where('description', 'like', 'تبرع لخزينة الفريق:%')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount'));

        return [
            'has_donation' => $hasDonation,
            'percentage' => $percentage,
            'base' => $base,
            'limit' => $limit,
            'donated' => $donated,
            'remaining' => max(0, $limit - $donated),
        ];
    }

    /**
     * Donate coins to team.
     */
    public static function donateCoinsToTeam(int $studentId, int $teamId, int $amount, &$error = null): bool
    {
        if ($amount <= 0) {
            $error = 'الرجاء إدخال مبلغ صحيح للتبرع.';

            return false;
        }

        $student = Student::findOrFail($studentId);
        $team = GamificationTeam::findOrFail($teamId);
        $leaderboardId = $team->leaderboard_id;

        $status = self::getDailyDonationStatus($studentId, $leaderboardId);

        if (! $status['has_donation']) {
            $error = 'ميزة التبرع للفريق غير مفعلة لمستواك الحالي.';

            return false;
        }

        if ($status['donated'] >= $status['limit']) {
            $error = "لقد وصلت للحد الأقصى للتبرع اليومي المسموح به لمستواك وهو ({$status['limit']} عملة - يعادل {$status['percentage']}% من رصيد عملاتك في بداية اليوم: {$status['base']} عملة).";

            return false;
        }

        if ($status['limit'] < $status['donated'] + $amount) {
            $error = "المبلغ المتبقي المسموح لك بالتبرع به اليوم هو {$status['remaining']} عملة فقط (نسبة {$status['percentage']}% من رصيد عملاتك في بداية اليوم تعادل {$status['limit']} عملة).";

            return false;
        }

        $state = GamificationStudentState::where('leaderboard_id', $leaderboardId)
            ->where('student_id', $studentId)
            ->first();

        if (! $state || $state->coins < $amount) {
            $error = 'رصيدك الحالي غير كافٍ لإتمام التبرع.';

            return false;
        }

        DB::transaction(function () use ($studentId, $team, $amount, $leaderboardId) {
            GamificationTransaction::create([
                'leaderboard_id' => $leaderboardId,
                'student_id' => $studentId,
                'type' => 'spend',
                'amount' => -$amount,
                'description' => "تبرع لخزينة الفريق: {$team->name}",
            ]);

            $team->coins += $amount;
            $team->save();

            GamificationTransaction::create([
                'leaderboard_id' => $leaderboardId,
                'team_id' => $team->id,
                'student_id' => $studentId,
                'type' => 'earn',
                'amount' => $amount,
                'xp_amount' => 0, // coins-only transfer; the donor already scored these points when earned
                'description' => 'تبرع من الطالب: '.Student::find($studentId)->name,
            ]);

            self::recalculateStudentState($studentId, $leaderboardId);
        });

        return true;
    }

    /**
     * Request store purchase.
     */
    public static function requestStorePurchase(int $studentId, int $itemId, ?int $targetTeamId = null, ?string $targetDate = null, ?int $customPrice = null): string
    {
        $student = Student::findOrFail($studentId);
        $item = GamificationStoreItem::findOrFail($itemId);
        $leaderboardId = $item->leaderboard_id;
        $price = $customPrice !== null ? $customPrice : $item->price;

        $team = $student->gamificationTeams()->where('leaderboard_id', $leaderboardId)->first();

        // Validation for target_team_id (team_attack)
        if ($item->item_type === 'team_attack') {
            if (! $targetTeamId) {
                return 'target_team_required';
            }
            $targetTeamExists = GamificationTeam::where('leaderboard_id', $leaderboardId)
                ->where('id', $targetTeamId)
                ->exists();
            if (! $targetTeamExists) {
                return 'invalid_target_team';
            }
            if ($team && $team->id === (int) $targetTeamId) {
                return 'cannot_attack_own_team';
            }
        }

        // Validation for target_date (multiplier, shield)
        if (in_array($item->item_type, ['multiplier', 'shield'])) {
            if (! $targetDate && ! $item->target_date) {
                return 'target_date_required';
            }
            $resolvedDate = $targetDate ?: ($item->target_date instanceof \DateTimeInterface ? $item->target_date->format('Y-m-d') : $item->target_date);
            $targetCarbonDate = Carbon::parse($resolvedDate)->startOfDay();
            $tomorrow = Carbon::now('Asia/Riyadh')->addDay()->startOfDay();
            if ($targetCarbonDate->lt($tomorrow)) {
                return 'invalid_target_date';
            }
            $targetDate = $resolvedDate;

            if ($item->item_type === 'multiplier') {
                if ($item->is_team_product) {
                    if (! $team) {
                        return 'must_be_in_team';
                    }
                    $existingPurchase = GamificationStorePurchase::whereIn('status', ['approved', 'pending_approval'])
                        ->where('team_id', $team->id)
                        ->whereHas('item', function ($q) {
                            $q->where('item_type', 'multiplier');
                        })
                        ->whereDate('target_date', $targetDate)
                        ->exists();
                } else {
                    $existingPurchase = GamificationStorePurchase::whereIn('status', ['approved', 'pending_approval'])
                        ->where('student_id', $studentId)
                        ->whereNull('team_id')
                        ->whereHas('item', function ($q) {
                            $q->where('item_type', 'multiplier');
                        })
                        ->whereDate('target_date', $targetDate)
                        ->exists();
                }

                if ($existingPurchase) {
                    return $item->is_team_product ? 'team_multiplier_already_purchased' : 'individual_multiplier_already_purchased';
                }
            }
        }

        // Validation for freeze (Streak Freeze) target date
        if ($item->item_type === 'freeze') {
            if (! $targetDate) {
                return 'target_date_required';
            }
            $targetDate = Carbon::parse($targetDate)->format('Y-m-d');
            $freezeableDates = self::getFreezeableDates($student, $item->leaderboard);
            if (! in_array($targetDate, $freezeableDates)) {
                return 'invalid_target_date';
            }

            $alreadyFrozen = GamificationStorePurchase::where('student_id', $studentId)
                ->where('status', 'approved')
                ->where('target_date', $targetDate)
                ->whereHas('item', fn ($q) => $q->where('is_streak_freeze', true))
                ->exists();

            if ($alreadyFrozen) {
                return 'already_frozen';
            }
        }

        if ($item->is_team_product) {
            if (! $team) {
                return 'must_be_in_team';
            }

            $role = $team->pivot->role;
            if ($role !== 'leader') {
                return 'only_leader';
            }

            if ($team->coins < $price) {
                return 'insufficient_team_coins';
            }

            $leaderboard = $item->leaderboard;
            $settings = $leaderboard->settings ?? [];
            $requiresVoting = $item->require_assistant_approval || $item->require_member_approval_count > 0;

            if (! $requiresVoting && ($settings['team_purchase_voting_enabled'] ?? false)) {
                $requiresVoting = true;
            }

            if ($requiresVoting) {
                $memberCount = $team->students()->count();
                if ($memberCount > 1) {
                    $purchase = GamificationStorePurchase::create([
                        'store_item_id' => $item->id,
                        'student_id' => $studentId,
                        'team_id' => $team->id,
                        'status' => 'pending_approval',
                        'price_paid' => $price,
                        'target_team_id' => $targetTeamId,
                        'target_date' => $targetDate ? Carbon::parse($targetDate)->format('Y-m-d') : null,
                    ]);

                    DB::table('gamification_purchase_votes')->insert([
                        'store_purchase_id' => $purchase->id,
                        'student_id' => $studentId,
                        'vote' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    self::checkPurchaseVotingStatus($purchase->id);
                    $purchase->refresh();

                    if ($purchase->status === 'approved') {
                        return 'success';
                    }

                    return 'pending_voting';
                }
            }

            DB::transaction(function () use ($team, $item, $studentId, $leaderboardId, $targetTeamId, $targetDate, $price) {
                $team->coins -= $price;
                $team->save();

                $purchase = GamificationStorePurchase::create([
                    'store_item_id' => $item->id,
                    'student_id' => $studentId,
                    'team_id' => $team->id,
                    'status' => 'approved',
                    'price_paid' => $price,
                    'target_team_id' => $targetTeamId,
                    'target_date' => $targetDate ? Carbon::parse($targetDate)->format('Y-m-d') : null,
                ]);

                GamificationTransaction::create([
                    'leaderboard_id' => $leaderboardId,
                    'team_id' => $team->id,
                    'student_id' => $studentId,
                    'type' => 'spend',
                    'amount' => -$price,
                    'description' => "شراء من المتجر: {$item->name}",
                    'reference_type' => GamificationStorePurchase::class,
                    'reference_id' => $purchase->id,
                ]);

                self::executePurchase($purchase);
            });

            return 'success';
        } else {
            $state = GamificationStudentState::where('leaderboard_id', $leaderboardId)
                ->where('student_id', $studentId)
                ->first();

            if (! $state || $state->coins < $price) {
                return 'insufficient_coins';
            }

            DB::transaction(function () use ($item, $studentId, $leaderboardId, $targetTeamId, $targetDate, $price) {
                GamificationTransaction::create([
                    'leaderboard_id' => $leaderboardId,
                    'student_id' => $studentId,
                    'type' => 'spend',
                    'amount' => -$price,
                    'description' => "شراء من المتجر: {$item->name}",
                ]);

                $purchase = GamificationStorePurchase::create([
                    'store_item_id' => $item->id,
                    'student_id' => $studentId,
                    'status' => 'approved',
                    'price_paid' => $price,
                    'target_team_id' => $targetTeamId,
                    'target_date' => $targetDate ? Carbon::parse($targetDate)->format('Y-m-d') : null,
                ]);

                self::executePurchase($purchase);
                self::recalculateStudentState($studentId, $leaderboardId);
            });

            return 'success';
        }
    }

    /**
     * Check the voting status and approve/reject the purchase if conditions are met.
     */
    public static function checkPurchaseVotingStatus(int $purchaseId): void
    {
        $purchase = GamificationStorePurchase::findOrFail($purchaseId);
        if ($purchase->status !== 'pending_approval') {
            return;
        }

        $team = $purchase->team;
        $item = $purchase->item;
        $members = $team->students()->get();
        $totalMembersCount = $members->count();
        $assistants = $members->filter(fn ($s) => $s->pivot->role === 'assistant');

        $votes = DB::table('gamification_purchase_votes')->where('store_purchase_id', $purchaseId)->get();
        $yesVotesCount = $votes->where('vote', true)->count();
        $noVotesCount = $votes->where('vote', false)->count();

        // 1. Check if requirements are MET (Approved)
        $assistantApprovalMet = true;
        if ($item->require_assistant_approval && $assistants->isNotEmpty()) {
            // Check if any assistant voted true
            $assistantApprovalMet = $votes->whereIn('student_id', $assistants->pluck('id'))->where('vote', true)->isNotEmpty();
        }

        $threshold = $item->require_member_approval_count;
        if ($threshold <= 0 && ! $item->require_assistant_approval) {
            $threshold = intval($totalMembersCount / 2) + 1;
        }

        $memberApprovalMet = true;
        if ($threshold > 0) {
            $memberApprovalMet = $yesVotesCount >= $threshold;
        }

        if ($assistantApprovalMet && $memberApprovalMet) {
            DB::transaction(function () use ($purchase, $team) {
                $team->coins -= $purchase->price_paid;
                $team->save();

                $purchase->update(['status' => 'approved']);

                GamificationTransaction::create([
                    'leaderboard_id' => $team->leaderboard_id,
                    'team_id' => $team->id,
                    'student_id' => $purchase->student_id,
                    'type' => 'spend',
                    'amount' => -$purchase->price_paid,
                    'description' => "شراء معتمد بالتصويت: {$purchase->item->name}",
                    'reference_type' => GamificationStorePurchase::class,
                    'reference_id' => $purchase->id,
                ]);

                self::executePurchase($purchase);
            });

            return;
        }

        // 2. Check if requirements CANNOT be met (Rejected)
        $rejected = false;

        // If assistant approval is required but all assistants voted false
        if ($item->require_assistant_approval && $assistants->isNotEmpty()) {
            $assistantYesVotes = $votes->whereIn('student_id', $assistants->pluck('id'))->where('vote', true)->count();
            $assistantNoVotes = $votes->whereIn('student_id', $assistants->pluck('id'))->where('vote', false)->count();
            if ($assistantYesVotes === 0 && $assistantNoVotes === $assistants->count()) {
                $rejected = true;
            }
        }

        // If member approval count is required but maximum possible yes votes is less than required
        if ($threshold > 0) {
            $remainingVotesCount = $totalMembersCount - ($yesVotesCount + $noVotesCount);
            if (($yesVotesCount + $remainingVotesCount) < $threshold) {
                $rejected = true;
            }
        }

        // Or if all members voted and requirements are not met
        if (($yesVotesCount + $noVotesCount) === $totalMembersCount) {
            if (! $assistantApprovalMet || ! $memberApprovalMet) {
                $rejected = true;
            }
        }

        if ($rejected) {
            $purchase->update(['status' => 'rejected']);
        }
    }

    /**
     * Vote for a pending purchase.
     */
    public static function voteForPurchase(int $studentId, int $purchaseId, bool $vote): bool
    {
        $purchase = GamificationStorePurchase::findOrFail($purchaseId);
        if ($purchase->status !== 'pending_approval') {
            return false;
        }

        $student = Student::findOrFail($studentId);
        $team = $purchase->team;

        if (! $team->students()->where('students.id', $studentId)->exists()) {
            return false;
        }

        DB::table('gamification_purchase_votes')->updateOrInsert(
            ['store_purchase_id' => $purchaseId, 'student_id' => $studentId],
            ['vote' => $vote, 'created_at' => now(), 'updated_at' => now()]
        );

        self::checkPurchaseVotingStatus($purchaseId);

        return true;
    }

    /**
     * Execute purchase effect.
     */
    public static function executePurchase(GamificationStorePurchase $purchase): void
    {
        $item = $purchase->item;
        $student = $purchase->student;
        $team = $purchase->team;
        $leaderboardId = $item->leaderboard_id;

        if ($item->item_type === 'team_points') {
            if ($team) {
                GamificationTransaction::create([
                    'leaderboard_id' => $leaderboardId,
                    'team_id' => $team->id,
                    'type' => 'earn',
                    'amount' => $item->value,
                    'description' => "تفعيل قوة ميزة دعم الفريق: +{$item->value} نقاط",
                ]);
            }
        } elseif ($item->is_streak_freeze) {
            $state = GamificationStudentState::firstOrCreate([
                'leaderboard_id' => $leaderboardId,
                'student_id' => $student->id,
            ]);
            $state->streak_freezes_count++;
            $state->save();
        } elseif ($item->item_type === 'team_attack') {
            if ($purchase->target_team_id) {
                $targetTeam = GamificationTeam::find($purchase->target_team_id);
                if ($targetTeam) {
                    $executionDate = Carbon::now('Asia/Riyadh')->format('Y-m-d');
                    $isShieldActive = self::isShieldActiveForTeam($targetTeam, $executionDate);

                    if ($isShieldActive) {
                        // Attack blocked by shield
                        GamificationTransaction::create([
                            'leaderboard_id' => $leaderboardId,
                            'team_id' => $targetTeam->id,
                            'type' => 'spend',
                            'amount' => 0,
                            'description' => 'تم صد هجوم خصم النقاط من فريق '.($purchase->team ? $purchase->team->name : 'فريق منافس').' بفضل درع الحماية!',
                        ]);

                        // News: the attacker is intentionally not named.
                        GamificationNewsService::record($leaderboardId, 'team_attack_blocked', [
                            'target_team_name' => $targetTeam->name,
                        ]);
                    } else {
                        // Deduct points/coins from target team treasury
                        $teamAttackValue = (int) $item->value;
                        $targetTeam->coins -= $teamAttackValue;
                        $targetTeam->save();

                        GamificationTransaction::create([
                            'leaderboard_id' => $leaderboardId,
                            'team_id' => $targetTeam->id,
                            'type' => 'spend',
                            'amount' => -$teamAttackValue,
                            'description' => 'خصم عملات بسبب هجوم من فريق '.($purchase->team ? $purchase->team->name : 'فريق منافس').": -{$teamAttackValue} عملة",
                        ]);

                        // News: the attacker is intentionally not named.
                        GamificationNewsService::record($leaderboardId, 'team_attack', [
                            'target_team_name' => $targetTeam->name,
                            'amount' => $teamAttackValue,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Get active multiplier factor for a team on a given date.
     */
    public static function getMultiplierForTeam(GamificationTeam $team, $date): int
    {
        $formattedDate = Carbon::parse($date)->format('Y-m-d');

        $purchasedMultipliers = GamificationStorePurchase::where('team_id', $team->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($formattedDate) {
                $query->whereDate('target_date', $formattedDate)
                    ->orWhereHas('item', function ($q) use ($formattedDate) {
                        $q->where('item_type', 'multiplier')
                            ->whereDate('target_date', $formattedDate);
                    });
            })
            ->with('item')
            ->get();

        if ($purchasedMultipliers->isNotEmpty()) {
            $maxMultiplier = $purchasedMultipliers->max(function ($purchase) {
                return max(2, $purchase->item->value);
            });

            return (int) $maxMultiplier;
        }

        if ($team->multiplier_active_until) {
            if (Carbon::parse($date)->startOfDay()->lte($team->multiplier_active_until)) {
                return 2;
            }
        }

        return 1;
    }

    /**
     * Get active multiplier factor for a student on a given date.
     */
    public static function getMultiplierForStudent(Student $student, int $leaderboardId, $date): int
    {
        $formattedDate = Carbon::parse($date)->format('Y-m-d');

        $purchasedMultipliers = GamificationStorePurchase::where('student_id', $student->id)
            ->whereNull('team_id')
            ->where('status', 'approved')
            ->where(function ($query) use ($formattedDate) {
                $query->whereDate('target_date', $formattedDate)
                    ->orWhereHas('item', function ($q) use ($formattedDate) {
                        $q->where('item_type', 'multiplier')
                            ->whereDate('target_date', $formattedDate);
                    });
            })
            ->with('item')
            ->get();

        if ($purchasedMultipliers->isNotEmpty()) {
            $maxMultiplier = $purchasedMultipliers->max(function ($purchase) {
                return max(2, $purchase->item->value);
            });

            return (int) $maxMultiplier;
        }

        return 1;
    }

    /**
     * Check if a multiplier is active for a team on a given date.
     */
    public static function isMultiplierActiveForTeam(GamificationTeam $team, $date): bool
    {
        return self::getMultiplierForTeam($team, $date) > 1;
    }

    /**
     * Check if a shield is active for a team on a given date.
     */
    public static function isShieldActiveForTeam(GamificationTeam $team, $date): bool
    {
        $formattedDate = Carbon::parse($date)->format('Y-m-d');

        $hasPurchasedShieldForDate = GamificationStorePurchase::where('team_id', $team->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($formattedDate) {
                $query->whereDate('target_date', $formattedDate)
                    ->orWhereHas('item', function ($q) use ($formattedDate) {
                        $q->where('item_type', 'shield')
                            ->whereDate('target_date', $formattedDate);
                    });
            })
            ->exists();

        if ($hasPurchasedShieldForDate) {
            return true;
        }

        if ($team->shield_active_until) {
            return Carbon::parse($date)->startOfDay()->lte($team->shield_active_until);
        }

        return false;
    }

    /**
     * Get details about the next level upgrade for streak freezes.
     *
     * @return array<string, mixed>|null
     */
    public static function getNextFreezeUpgrade(Student $student, Leaderboard $leaderboard): ?array
    {
        $levelInfo = self::getStudentLevel($student->id, $leaderboard->id);
        $currentLevel = $levelInfo['current'] ?? null;
        $currentLevelNum = $currentLevel->level_number ?? 1;
        $currentMax = self::getStudentMaxFreezeDays($student, $leaderboard);

        // Get all levels for this leaderboard
        $levels = GamificationLevel::where('leaderboard_id', $leaderboard->id)
            ->orderBy('level_number', 'asc')
            ->get();

        if ($levels->isEmpty()) {
            $theme = GamificationThemeService::getTheme($leaderboard);
            $levels = collect($theme['default_levels'] ?? [])->map(fn ($lvl, $num) => (object) [
                'level_number' => $num,
                'name' => $lvl['name'],
                'xp_required' => $lvl['xp_required'],
                'icon' => $lvl['icon'] ?? 'sparkles',
                'settings' => [
                    'has_individual_multiplier' => true,
                    'individual_multiplier_price' => 150,
                    'has_team_multiplier' => true,
                    'team_multiplier_price' => 150,
                    'has_freeze' => true,
                    'freeze_price' => 100,
                    'freeze_max_days' => 1,
                    'has_donation' => true,
                    'donation_max_limit' => 10,
                ],
            ]);
        }

        foreach ($levels as $level) {
            $levelNum = $level->level_number;
            if ($levelNum > $currentLevelNum) {
                $lvlSettings = is_array($level->settings) ? $level->settings : (array) ($level->settings ?? []);
                $days = (int) ($lvlSettings['freeze_max_days'] ?? 1);
                if ($days > $currentMax) {
                    return [
                        'level' => $levelNum,
                        'days' => $days,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get the maximum number of previous days a student can freeze.
     */
    public static function getStudentMaxFreezeDays(Student $student, Leaderboard $leaderboard): int
    {
        $levelInfo = self::getStudentLevel($student->id, $leaderboard->id);
        $level = $levelInfo['current'] ?? null;
        if ($level) {
            $lvlSettings = is_array($level->settings) ? $level->settings : (array) ($level->settings ?? []);

            return (int) ($lvlSettings['freeze_max_days'] ?? 1);
        }

        return 1;
    }

    /**
     * Get the list of dates that are currently freezeable for a student.
     *
     * @return array<string>
     */
    public static function getFreezeableDates(Student $student, Leaderboard $leaderboard): array
    {
        $settings = $leaderboard->settings ?? [];
        if (! ($settings['enthusiasm_enabled'] ?? false)) {
            return [];
        }

        $levelInfo = self::getStudentLevel($student->id, $leaderboard->id);
        $level = $levelInfo['current'] ?? null;
        if ($level) {
            $lvlSettings = is_array($level->settings) ? $level->settings : (array) ($level->settings ?? []);
            if (! ($lvlSettings['has_freeze'] ?? true)) {
                return [];
            }
        }

        // Get all working days for the leaderboard
        $workingDays = self::getWorkingDaysForLeaderboard($leaderboard);
        if (empty($workingDays)) {
            return [];
        }

        $todayStr = now('Asia/Riyadh')->format('Y-m-d');

        // Find the index of the latest working day that is <= today
        $latestIdx = -1;
        foreach ($workingDays as $idx => $dateStr) {
            if ($dateStr <= $todayStr) {
                $latestIdx = $idx;
            } else {
                break;
            }
        }

        if ($latestIdx === -1) {
            return [];
        }

        $maxDays = self::getStudentMaxFreezeDays($student, $leaderboard);

        // We can freeze from index: max(0, latestIdx - maxDays) to latestIdx
        $freezeableRange = [];
        $startIdx = max(0, $latestIdx - $maxDays);
        for ($i = $startIdx; $i <= $latestIdx; $i++) {
            $freezeableRange[] = $workingDays[$i];
        }

        return $freezeableRange;
    }

    /**
     * Get total earned XP for a student in a leaderboard.
     */
    public static function getStudentXP(int $studentId, int $leaderboardId): int
    {
        return (int) GamificationTransaction::where('leaderboard_id', $leaderboardId)
            ->where('student_id', $studentId)
            ->where('type', 'earn')
            ->claimed()
            ->sum('xp_amount');
    }

    /**
     * Pending (unclaimed) reward transactions for a student in a leaderboard.
     *
     * @return Collection<int, GamificationTransaction>
     */
    public static function getPendingRewards(int $studentId, int $leaderboardId)
    {
        return GamificationTransaction::where('student_id', $studentId)
            ->where('leaderboard_id', $leaderboardId)
            ->where('type', 'earn')
            ->unclaimed()
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Claim a single pending reward; only the owning student may claim it.
     */
    public static function claimReward(int $transactionId, int $studentId): bool
    {
        $tx = GamificationTransaction::whereKey($transactionId)
            ->where('student_id', $studentId)
            ->unclaimed()
            ->first();

        if (! $tx) {
            return false;
        }

        $tx->claimed_at = now();
        $tx->save();

        self::recalculateStudentState($studentId, $tx->leaderboard_id);
        self::syncStudentBadges($studentId, $tx->leaderboard_id);

        return true;
    }

    /**
     * Claim every pending reward for a student in a leaderboard.
     *
     * @return int Number of rewards claimed.
     */
    public static function claimAllRewards(int $studentId, int $leaderboardId): int
    {
        $ids = GamificationTransaction::where('student_id', $studentId)
            ->where('leaderboard_id', $leaderboardId)
            ->unclaimed()
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        GamificationTransaction::whereIn('id', $ids)->update(['claimed_at' => now()]);

        self::recalculateStudentState($studentId, $leaderboardId);
        self::syncStudentBadges($studentId, $leaderboardId);

        return $ids->count();
    }

    /**
     * Get the total team score for a team in a leaderboard,
     * applying the team multiplier only to the team score.
     */
    public static function getTeamScore(GamificationTeam $team, Leaderboard $leaderboard): int
    {
        $teamStudents = $team->students()->get();
        $teamStudentIds = $teamStudents->pluck('id')->toArray();

        // Get all earn transactions for this team (via students or direct team earnings)
        $transactions = GamificationTransaction::where('leaderboard_id', $leaderboard->id)
            ->where(function ($query) use ($teamStudentIds, $team) {
                $query->whereIn('student_id', $teamStudentIds)
                    ->orWhere(fn ($q) => $q->whereNull('student_id')->where('team_id', $team->id));
            })
            ->where('type', 'earn')
            ->claimed()
            ->get();

        // Pre-fetch related models to get their dates and avoid N+1 queries
        $attendanceIds = $transactions->where('reference_type', Attendance::class)
            ->pluck('reference_id')
            ->unique()
            ->toArray();
        $attendances = empty($attendanceIds) ? collect() : Attendance::whereIn('id', $attendanceIds)->get()->keyBy('id');

        $planDayIds = $transactions->where('reference_type', StudentPlanDay::class)
            ->pluck('reference_id')
            ->unique()
            ->toArray();
        $planDays = empty($planDayIds) ? collect() : StudentPlanDay::whereIn('id', $planDayIds)->get()->keyBy('id');

        $scoreIds = $transactions->where('reference_type', LeaderboardScore::class)
            ->pluck('reference_id')
            ->unique()
            ->toArray();
        $scores = empty($scoreIds) ? collect() : LeaderboardScore::whereIn('id', $scoreIds)->get()->keyBy('id');

        $odeIds = $transactions->where('reference_type', StudentOdeAchievement::class)
            ->pluck('reference_id')
            ->unique()
            ->toArray();
        $odeAchievements = empty($odeIds) ? collect() : StudentOdeAchievement::with('pathDay')->whereIn('id', $odeIds)->get()->keyBy('id');

        $hadithIds = $transactions->where('reference_type', StudentHadithAchievement::class)
            ->pluck('reference_id')
            ->unique()
            ->toArray();
        $hadithAchievements = empty($hadithIds) ? collect() : StudentHadithAchievement::with('pathDay')->whereIn('id', $hadithIds)->get()->keyBy('id');

        $totalTeamScore = 0;

        foreach ($transactions as $tx) {
            $date = null;
            $hasMultiplier = false;

            if ($tx->reference_type === Attendance::class) {
                $att = $attendances->get($tx->reference_id);
                if ($att) {
                    $date = $att->date;
                    $hasMultiplier = true;
                }
            } elseif ($tx->reference_type === StudentPlanDay::class) {
                $day = $planDays->get($tx->reference_id);
                if ($day) {
                    $date = $day->date;
                    $hasMultiplier = true;
                }
            } elseif ($tx->reference_type === LeaderboardScore::class) {
                $sc = $scores->get($tx->reference_id);
                if ($sc) {
                    $date = $sc->date;
                    $hasMultiplier = true;
                }
            } elseif ($tx->reference_type === StudentOdeAchievement::class) {
                $ach = $odeAchievements->get($tx->reference_id);
                if ($ach) {
                    $date = $ach->hifz_graded_at ?? $ach->review_graded_at ?? $ach->pathDay?->date;
                    $hasMultiplier = (bool) $date;
                }
            } elseif ($tx->reference_type === StudentHadithAchievement::class) {
                $ach = $hadithAchievements->get($tx->reference_id);
                if ($ach) {
                    $date = $ach->hifz_graded_at ?? $ach->review_graded_at ?? $ach->pathDay?->date;
                    $hasMultiplier = (bool) $date;
                }
            }

            if ($hasMultiplier && $date) {
                // Determine the student's individual multiplier on this date
                $student = $teamStudents->firstWhere('id', $tx->student_id);
                $individualMultiplier = 1;
                if ($student) {
                    $individualMultiplier = self::getMultiplierForStudent($student, $leaderboard->id, $date);
                }

                // Determine the team multiplier on this date
                $teamMultiplier = self::getMultiplierForTeam($team, $date);

                // Effective multiplier is max of individual and team multiplier, up to 2
                $effectiveMultiplier = max($individualMultiplier, $teamMultiplier);
                $effectiveMultiplier = min(2, $effectiveMultiplier);

                // Since $tx->xp_amount has individual multiplier applied, the base XP was:
                $baseXp = (int) round($tx->xp_amount / $individualMultiplier);

                // Team receives base XP multiplied by the effective multiplier
                $totalTeamScore += ($baseXp * $effectiveMultiplier);
            } else {
                // Other transactions (like extra points or milestones) do not receive team multiplier
                $totalTeamScore += $tx->xp_amount;
            }
        }

        return $totalTeamScore;
    }

    /**
     * Get student level and next level details.
     *
     * @return array<string, mixed>
     */
    public static function getStudentLevel(int $studentId, int $leaderboardId): array
    {
        $xp = self::getStudentXP($studentId, $leaderboardId);
        $levels = GamificationLevel::where('leaderboard_id', $leaderboardId)
            ->orderBy('level_number', 'asc')
            ->get();

        if ($levels->isEmpty()) {
            $leaderboard = Leaderboard::find($leaderboardId);
            $theme = GamificationThemeService::getTheme($leaderboard);
            $levels = collect($theme['default_levels'] ?? [])->map(fn ($lvl, $num) => (object) [
                'level_number' => $num,
                'name' => $lvl['name'],
                'xp_required' => $lvl['xp_required'],
                'icon' => $lvl['icon'] ?? 'sparkles',
                'settings' => [
                    'has_individual_multiplier' => true,
                    'individual_multiplier_price' => 150,
                    'has_team_multiplier' => true,
                    'team_multiplier_price' => 150,
                    'has_freeze' => true,
                    'freeze_price' => 100,
                    'freeze_max_days' => 1,
                    'has_donation' => true,
                    'donation_max_limit' => 10,
                ],
            ]);
        }

        $currentLevel = null;
        $nextLevel = null;

        foreach ($levels as $level) {
            if ($xp >= $level->xp_required) {
                $currentLevel = $level;
            } else {
                $nextLevel = $level;
                break;
            }
        }

        if (! $currentLevel && $levels->isNotEmpty()) {
            $currentLevel = $levels->first();
        }

        return [
            'xp' => $xp,
            'current' => $currentLevel,
            'next' => $nextLevel,
        ];
    }

    /**
     * Calculate the maximum consecutive day streak from a list of dates.
     */
    public static function calculateMaxStreakOfDates($dates): int
    {
        $datesArray = is_array($dates) ? $dates : (method_exists($dates, 'toArray') ? $dates->toArray() : (array) $dates);
        if (empty($datesArray)) {
            return 0;
        }
        $maxStreak = 0;
        $currentStreak = 0;
        $prevDate = null;
        foreach ($datesArray as $dateStr) {
            $date = Carbon::parse($dateStr)->startOfDay();
            if ($prevDate === null) {
                $currentStreak = 1;
            } else {
                $diff = (int) $date->diffInDays($prevDate, true);
                if ($diff === 1) {
                    $currentStreak++;
                } elseif ($diff > 1) {
                    $maxStreak = max($maxStreak, $currentStreak);
                    $currentStreak = 1;
                }
            }
            $prevDate = $date;
        }

        return max($maxStreak, $currentStreak);
    }

    /**
     * Sync and automatically award/revoke all badges of all types for a student.
     */
    public static function syncStudentBadges(int $studentId, int $leaderboardId): void
    {
        $leaderboard = Leaderboard::find($leaderboardId);
        if (! $leaderboard) {
            return;
        }

        $startDate = $leaderboard->start_date;
        $endDate = $leaderboard->end_date ?? now()->format('Y-m-d');

        $badges = GamificationBadge::where('leaderboard_id', $leaderboardId)->get();

        foreach ($badges as $badge) {
            $value = 0;

            switch ($badge->badge_type) {
                case 'streak_attendance':
                case 'attendance_streak':
                    $dates = Attendance::where('student_id', $studentId)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereIn('status', ['present', 'late'])
                        ->orderBy('date', 'asc')
                        ->pluck('date')
                        ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
                        ->unique()
                        ->values();
                    $value = self::calculateMaxStreakOfDates($dates);
                    break;

                case 'count_attendance':
                    $value = Attendance::where('student_id', $studentId)
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereIn('status', ['present', 'late'])
                        ->count();
                    break;

                case 'streak_hifz':
                case 'hifz_streak':
                    $dates = StudentPlanDay::whereHas('plan', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId)->where('is_approved', 1);
                    })
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereIn('hifz_achievement', [1, 2, 3, '1', '2', '3', 'acceptable', 'good', 'excellent'])
                        ->orderBy('date', 'asc')
                        ->pluck('date')
                        ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
                        ->unique()
                        ->values();
                    $value = self::calculateMaxStreakOfDates($dates);
                    break;

                case 'count_hifz':
                    $value = StudentPlanDay::whereHas('plan', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId)->where('is_approved', 1);
                    })
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereIn('hifz_achievement', [1, 2, 3, '1', '2', '3', 'acceptable', 'good', 'excellent'])
                        ->count();
                    break;

                case 'streak_review':
                    $dates = StudentPlanDay::whereHas('plan', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId)->where('is_approved', 1);
                    })
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereIn('review_achievement', [1, 2, 3, '1', '2', '3', 'acceptable', 'good', 'excellent'])
                        ->orderBy('date', 'asc')
                        ->pluck('date')
                        ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
                        ->unique()
                        ->values();
                    $value = self::calculateMaxStreakOfDates($dates);
                    break;

                case 'count_review':
                    $value = StudentPlanDay::whereHas('plan', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId)->where('is_approved', 1);
                    })
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereIn('review_achievement', [1, 2, 3, '1', '2', '3', 'acceptable', 'good', 'excellent'])
                        ->count();
                    break;

                case 'streak_criterion':
                    if ($badge->leaderboard_criterion_id) {
                        $dates = LeaderboardScore::where('leaderboard_id', $leaderboardId)
                            ->where('student_id', $studentId)
                            ->where('leaderboard_criterion_id', $badge->leaderboard_criterion_id)
                            ->orderBy('date', 'asc')
                            ->pluck('date')
                            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
                            ->unique()
                            ->values();
                        $value = self::calculateMaxStreakOfDates($dates);
                    }
                    break;

                case 'count_criterion':
                case 'custom':
                    if ($badge->leaderboard_criterion_id) {
                        $value = LeaderboardScore::where('leaderboard_id', $leaderboardId)
                            ->where('student_id', $studentId)
                            ->where('leaderboard_criterion_id', $badge->leaderboard_criterion_id)
                            ->count();
                    } else {
                        // Manual custom badge - skip auto syncing
                        continue 2;
                    }
                    break;

                default:
                    // Manual/custom - don't sync automatically
                    continue 2;
            }

            $existing = DB::table('gamification_badge_student')
                ->where('badge_id', $badge->id)
                ->where('student_id', $studentId)
                ->first();

            if ($value >= $badge->requirement_value) {
                if (! $existing) {
                    DB::table('gamification_badge_student')->insert([
                        'badge_id' => $badge->id,
                        'student_id' => $studentId,
                        'status' => 'pending_approval',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    GamificationNewsService::record($leaderboardId, 'badge', [
                        'student_id' => $studentId,
                        'student_name' => Student::find($studentId)?->name ?? '',
                        'badge_name' => $badge->name,
                        'badge_icon' => $badge->icon,
                    ]);
                }
            } else {
                if ($existing && $existing->status !== 'claimed') {
                    DB::table('gamification_badge_student')
                        ->where('badge_id', $badge->id)
                        ->where('student_id', $studentId)
                        ->delete();
                }
            }
        }
    }
}
