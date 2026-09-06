<?php

namespace App\Services\Reports;

use App\Support\Access;
use App\Support\Scope;

/**
 * The reports the application knows how to produce.
 *
 * Adding one is an entry here, not a screen: what it measures is its own, and
 * who may read it and how much of the academy they see are settled elsewhere —
 * by the permission on its screen and by `Scope`. That is what lets a single
 * report serve every role rather than being rewritten once per role, which is
 * how five of them came to exist in two copies each.
 */
class ReportCatalogue
{
    /** @return array<int, Report> */
    public static function all(): array
    {
        return [
            // The student's own record.
            app(AttendanceReport::class),
            app(MemorizationReport::class),
            app(SelfProgramReport::class),
            app(MutunReport::class),
            app(ExamsReport::class),
            app(GamificationReport::class),
            app(RetentionReport::class),
            app(FamilyContactReport::class),

            // And those whose subject is not the student.
            app(TeacherPerformanceReport::class),
            app(SupervisionReport::class),
            app(FormsReport::class),
            app(TasksReport::class),
        ];
    }

    public static function find(string $key): ?Report
    {
        foreach (self::all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }

    /**
     * The reports open to a reader.
     *
     * Each answers to a screen of its own — `<role>.reports.<key>` — so it is
     * granted, withheld, or opened for one person alone from the same screen
     * that governs every other page.
     *
     * @return array<int, Report>
     */
    public static function for(Scope $scope): array
    {
        return array_values(array_filter(
            self::all(),
            fn (Report $report) => Access::canSee(
                $scope->user(),
                $scope->role(),
                $scope->role().'.reports.'.$report->key(),
            ),
        ));
    }
}
