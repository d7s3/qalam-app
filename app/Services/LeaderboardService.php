<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Leaderboard;
use App\Models\LeaderboardScore;
use App\Models\Student;
use App\Models\StudentPlanDay;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    public function getDetailedStandings(Leaderboard $leaderboard)
    {
        $isGamification = $leaderboard->competition_type === 'gamification';
        $relations = ['circle'];
        if ($isGamification) {
            $relations['gamificationTeams'] = function ($q) use ($leaderboard) {
                $q->where('leaderboard_id', $leaderboard->id);
            };
        }

        // For supervisor competitions, load students from all participating circles
        if ($leaderboard->isSupervisorCompetition()) {
            $circleIds = $leaderboard->circles()->pluck('circles.id')->toArray();
            if (empty($circleIds)) {
                $circleIds = [$leaderboard->circle_id];
            }
            $students = Student::whereIn('circle_id', $circleIds)->with($relations)->get();
        } else {
            $students = Student::where('circle_id', $leaderboard->circle_id)->with($relations)->get();
        }

        if ($students->isEmpty()) {
            return collect([]);
        }

        $startDate = $leaderboard->start_date->startOfDay();
        $endDate = $leaderboard->end_date ? $leaderboard->end_date->endOfDay() : now()->endOfDay();
        $settings = $leaderboard->settings ?? [];

        $isGamification = $leaderboard->competition_type === 'gamification';

        if ($isGamification) {
            // Get all earn transactions for this leaderboard
            $transactions = DB::table('gamification_transactions')
                ->where('leaderboard_id', $leaderboard->id)
                ->where('type', 'earn')
                ->get();

            // Pre-fetch related models to avoid N+1 queries
            $planDayIds = $transactions->where('reference_type', 'App\Models\StudentPlanDay')
                ->pluck('reference_id')
                ->unique()
                ->toArray();

            $planDays = [];
            if (! empty($planDayIds)) {
                $planDays = StudentPlanDay::whereIn('id', $planDayIds)->get()->keyBy('id');
            }

            $scoreIds = $transactions->where('reference_type', 'App\Models\LeaderboardScore')
                ->pluck('reference_id')
                ->unique()
                ->toArray();

            $scoreRecords = [];
            if (! empty($scoreIds)) {
                $scoreRecords = LeaderboardScore::whereIn('id', $scoreIds)->get()->keyBy('id');
            }
        }

        $standings = [];

        foreach ($students as $student) {
            $totalScore = 0;
            $manualScore = 0;
            $attendanceScore = 0;
            $hifzScore = 0;
            $reviewScore = 0;
            $criteriaCounts = [];
            $extraPointsScore = 0;

            $extraPointsList = DB::table('leaderboard_extra_points')
                ->where('leaderboard_id', $leaderboard->id)
                ->where('student_id', $student->id)
                ->get();

            if ($isGamification) {
                $studentTxs = $transactions->where('student_id', $student->id);
                $totalScore = (int) $studentTxs->sum('xp_amount');

                foreach ($studentTxs as $tx) {
                    if ($tx->reference_type === 'App\Models\Attendance') {
                        $attendanceScore += $tx->xp_amount;
                    } elseif ($tx->reference_type === 'App\Models\StudentPlanDay') {
                        $day = $planDays[$tx->reference_id] ?? null;
                        if ($day) {
                            $hifzBase = 0;
                            $reviewBase = 0;

                            if ($day->hifz_achievement !== null) {
                                $hifz = $day->hifz_achievement;
                                if ($hifz === 3 || $hifz === '3' || $hifz === 'excellent') {
                                    $hifzBase = ($settings['hifz_excellent'] ?? 10);
                                } elseif ($hifz === 2 || $hifz === '2' || $hifz === 'good') {
                                    $hifzBase = ($settings['hifz_good'] ?? 7);
                                } elseif ($hifz === 1 || $hifz === '1' || $hifz === 'acceptable') {
                                    $hifzBase = ($settings['hifz_acceptable'] ?? 4);
                                }
                            }

                            if ($day->review_achievement !== null) {
                                $review = $day->review_achievement;
                                if ($review === 3 || $review === '3' || $review === 'excellent') {
                                    $reviewBase = ($settings['review_excellent'] ?? 5);
                                } elseif ($review === 2 || $review === '2' || $review === 1 || $review === 1 || $review === 'good' || $review === 'acceptable') {
                                    $reviewBase = ($settings['review_good'] ?? 3);
                                }
                            }

                            $totalBase = $hifzBase + $reviewBase;
                            if ($totalBase > 0) {
                                $hifzShare = (int) round(($hifzBase / $totalBase) * $tx->xp_amount);
                                $hifzScore += $hifzShare;
                                $reviewScore += $tx->xp_amount - $hifzShare;
                            } else {
                                $hifzScore += $tx->xp_amount;
                            }
                        } else {
                            $hifzScore += $tx->xp_amount;
                        }
                    } elseif ($tx->reference_type === 'App\Models\LeaderboardScore') {
                        $scoreRecord = $scoreRecords[$tx->reference_id] ?? null;
                        if ($scoreRecord) {
                            $criterionId = $scoreRecord->leaderboard_criterion_id;
                            $criteriaCounts[$criterionId] = ($criteriaCounts[$criterionId] ?? 0) + 1;
                        }
                        $manualScore += $tx->xp_amount;
                    } elseif ($tx->reference_type === 'leaderboard_extra_points') {
                        $extraPointsScore += $tx->xp_amount;
                    } else {
                        // Milestone rewards, badges, and other gamification earn transactions
                        $extraPointsScore += $tx->xp_amount;
                    }
                }
            } else {
                // 1. Manual Criteria Scores
                $manualScoresList = DB::table('leaderboard_scores')
                    ->join('leaderboard_criteria', 'leaderboard_scores.leaderboard_criterion_id', '=', 'leaderboard_criteria.id')
                    ->where('leaderboard_scores.leaderboard_id', $leaderboard->id)
                    ->where('leaderboard_scores.student_id', $student->id)
                    ->select('leaderboard_criteria.id as criterion_id', 'leaderboard_criteria.name', 'leaderboard_criteria.points')
                    ->get();

                $manualScore = $manualScoresList->sum('points');

                // 1.5 Extra Points
                $extraPointsScore = $extraPointsList->sum('points');

                $totalScore += $manualScore + $extraPointsScore;

                // Count occurrences per criterion
                foreach ($manualScoresList as $ms) {
                    $criteriaCounts[$ms->criterion_id] = ($criteriaCounts[$ms->criterion_id] ?? 0) + 1;
                }

                // 2. Attendance Points
                if ($settings['attendance_enabled'] ?? false) {
                    $attendances = Attendance::where('student_id', $student->id)
                        ->where('date', '>=', $startDate->format('Y-m-d'))
                        ->where('date', '<=', $endDate->format('Y-m-d'))
                        ->get();

                    $attendanceScore += $attendances->where('status', 'present')->count() * ($settings['attendance_present'] ?? 4);
                    $attendanceScore += $attendances->where('status', 'late')->count() * ($settings['attendance_late'] ?? 2);
                    $totalScore += $attendanceScore;
                }

                // 3. Hifz & Review Points
                if (($settings['hifz_enabled'] ?? false) || ($settings['review_enabled'] ?? false)) {
                    $days = StudentPlanDay::whereHas('plan', function ($q) use ($student) {
                        $q->where('student_id', $student->id)
                            ->where('is_approved', 1);
                    })
                        ->where(function ($q) use ($startDate, $endDate) {
                            $q->whereBetween('hifz_graded_at', [$startDate, $endDate])
                                ->orWhereBetween('review_graded_at', [$startDate, $endDate])
                                ->orWhere(function ($sub) use ($startDate, $endDate) {
                                    $sub->whereNull('hifz_graded_at')
                                        ->whereNull('review_graded_at')
                                        ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                                        ->where(function ($s) {
                                            $s->whereNotNull('hifz_achievement')
                                                ->orWhereNotNull('review_achievement');
                                        });
                                });
                        })->get();

                    if ($settings['hifz_enabled'] ?? false) {
                        foreach ($days as $day) {
                            $gradedAt = $day->hifz_graded_at ?? $day->date;
                            if ($gradedAt >= $startDate && $gradedAt <= $endDate && $day->hifz_achievement !== null) {
                                $hifz = $day->hifz_achievement;
                                if ($hifz === 3 || $hifz === '3' || $hifz === 'excellent') {
                                    $hifzScore += ($settings['hifz_excellent'] ?? 10);
                                } elseif ($hifz === 2 || $hifz === '2' || $hifz === 'good') {
                                    $hifzScore += ($settings['hifz_good'] ?? 7);
                                } elseif ($hifz === 1 || $hifz === '1' || $hifz === 'acceptable') {
                                    $hifzScore += ($settings['hifz_acceptable'] ?? 4);
                                }
                            }
                        }
                    }

                    if ($settings['review_enabled'] ?? false) {
                        foreach ($days as $day) {
                            $gradedAt = $day->review_graded_at ?? $day->date;
                            if ($gradedAt >= $startDate && $gradedAt <= $endDate && $day->review_achievement !== null) {
                                $review = $day->review_achievement;
                                if ($review === 3 || $review === '3' || $review === 'excellent') {
                                    $reviewScore += ($settings['review_excellent'] ?? 5);
                                } elseif ($review === 2 || $review === '2' || $review === '1' || $review === 1 || $review === 'good' || $review === 'acceptable') {
                                    $reviewScore += ($settings['review_good'] ?? 3);
                                }
                            }
                        }
                    }
                    $totalScore += $hifzScore + $reviewScore;
                }
            }

            $standings[] = [
                'student' => $student,
                'score' => $totalScore,
                'details' => [
                    'manual' => $manualScore,
                    'extra_points_score' => $extraPointsScore,
                    'extra_points_list' => $extraPointsList,
                    'attendance' => $attendanceScore,
                    'hifz' => $hifzScore,
                    'review' => $reviewScore,
                    'criteria_counts' => $criteriaCounts,
                ],
            ];
        }

        $standings = collect($standings)->sortByDesc('score')->values();

        // Add rank for easier consumption in views
        return $standings->map(function ($item, $index) {
            $item['rank'] = $index + 1;

            return $item;
        });
    }

    // Proxy for original getStandings to not break student dashboard
    public function getStandings(Leaderboard $leaderboard)
    {
        return $this->getDetailedStandings($leaderboard);
    }

    public function getDailyScores(Leaderboard $leaderboard, $date)
    {
        // For supervisor competitions, include all participating circles
        if ($leaderboard->isSupervisorCompetition()) {
            $circleIds = $leaderboard->circles()->pluck('circles.id')->toArray();
            if (empty($circleIds)) {
                $circleIds = [$leaderboard->circle_id];
            }
            $students = Student::whereIn('circle_id', $circleIds)->get();
        } else {
            $students = Student::where('circle_id', $leaderboard->circle_id)->get();
        }
        $settings = $leaderboard->settings ?? [];

        $dailyScores = [];

        foreach ($students as $student) {
            $totalScore = 0;
            $manualScore = 0;
            $automatedScore = 0;
            $hifzScoreDaily = 0;
            $reviewScoreDaily = 0;
            $attendanceScoreDaily = 0;

            // 1. Manual Criteria Scores for TODAY
            $manualScore = DB::table('leaderboard_scores')
                ->join('leaderboard_criteria', 'leaderboard_scores.leaderboard_criterion_id', '=', 'leaderboard_criteria.id')
                ->where('leaderboard_scores.leaderboard_id', $leaderboard->id)
                ->where('leaderboard_scores.student_id', $student->id)
                ->whereDate('leaderboard_scores.date', $date)
                ->sum('leaderboard_criteria.points');

            $totalScore += $manualScore;

            // 2. Attendance Points for TODAY
            if ($settings['attendance_enabled'] ?? false) {
                $attendance = Attendance::where('student_id', $student->id)
                    ->whereDate('date', $date)
                    ->first();

                if ($attendance) {
                    if ($attendance->status === 'present') {
                        $attendanceScoreDaily = ($settings['attendance_present'] ?? 4);
                    } elseif ($attendance->status === 'late') {
                        $attendanceScoreDaily = ($settings['attendance_late'] ?? 2);
                    }
                    $automatedScore += $attendanceScoreDaily;
                }
            }

            // 3. Hifz & Review Points for TODAY
            if (($settings['hifz_enabled'] ?? false) || ($settings['review_enabled'] ?? false)) {
                $days = StudentPlanDay::whereHas('plan', function ($q) use ($student) {
                    $q->where('student_id', $student->id)
                        ->where('is_approved', 1);
                })
                    ->where(function ($q) use ($date) {
                        $q->whereDate('hifz_graded_at', $date)
                            ->orWhereDate('review_graded_at', $date)
                            ->orWhere(function ($sub) use ($date) {
                                $sub->whereNull('hifz_graded_at')
                                    ->whereNull('review_graded_at')
                                    ->whereDate('date', $date)
                                    ->where(function ($s) {
                                        $s->whereNotNull('hifz_achievement')
                                            ->orWhereNotNull('review_achievement');
                                    });
                            });
                    })->get();

                foreach ($days as $day) {
                    if ($settings['hifz_enabled'] ?? false) {
                        $gradedAt = $day->hifz_graded_at ? $day->hifz_graded_at->format('Y-m-d') : $day->date->format('Y-m-d');
                        if ($gradedAt === $date && $day->hifz_achievement !== null) {
                            $hifz = (int) $day->hifz_achievement;
                            if ($hifz === 3) {
                                $hifzScoreDaily += ($settings['hifz_excellent'] ?? 10);
                            } elseif ($hifz === 2) {
                                $hifzScoreDaily += ($settings['hifz_good'] ?? 7);
                            } elseif ($hifz === 1) {
                                $hifzScoreDaily += ($settings['hifz_acceptable'] ?? 4);
                            }
                        }
                    }

                    if ($settings['review_enabled'] ?? false) {
                        $gradedAt = $day->review_graded_at ? $day->review_graded_at->format('Y-m-d') : $day->date->format('Y-m-d');
                        if ($gradedAt === $date && $day->review_achievement !== null) {
                            $review = (int) $day->review_achievement;
                            if ($review === 3) {
                                $reviewScoreDaily += ($settings['review_excellent'] ?? 5);
                            } elseif ($review === 2 || $review === 1) {
                                $reviewScoreDaily += ($settings['review_good'] ?? 3);
                            }
                        }
                    }
                }
                $automatedScore += $hifzScoreDaily + $reviewScoreDaily;
            }

            $totalScore += $automatedScore;

            $dailyScores[$student->id] = [
                'total' => $totalScore,
                'manual' => $manualScore,
                'automated' => $automatedScore,
                'hifz' => $hifzScoreDaily,
                'review' => $reviewScoreDaily,
                'attendance' => $attendanceScoreDaily,
            ];
        }

        return $dailyScores;
    }
}
