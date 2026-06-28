<?php

namespace App\Console\Commands;

use App\Models\StudentPlanDay;
use App\Services\GamificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gamification:recompute-plan-day-points')]
#[Description('Re-sync gamification XP/coins for every graded hifz/review plan day (idempotent backfill).')]
class RecomputePlanDayPoints extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = 0;

        StudentPlanDay::query()
            ->where(function ($q) {
                $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement');
            })
            ->with('plan.student')
            ->chunkById(200, function ($days) use (&$count) {
                foreach ($days as $day) {
                    if ($day->plan?->student) {
                        GamificationService::syncStudentPlanDayXP($day);
                        $count++;
                    }
                }
            });

        $this->info("Recomputed gamification points for {$count} graded plan days.");

        return self::SUCCESS;
    }
}
