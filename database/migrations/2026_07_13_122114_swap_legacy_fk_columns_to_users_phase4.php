<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 cutover step: for every table backfilled in Phase 3, swap the OLD
 * column (pointing at one of the 6 legacy role tables) for the "_v2" column
 * (pointing at `users`) so relationships can keep using the original column
 * names. Done via two RENAME COLUMNs rather than DROP+RENAME — SQLite
 * refuses to DROP a column that's part of a foreign key or primary key
 * constraint, but RENAME COLUMN is explicitly supported even for such
 * columns (SQLite updates the constraint definition to track the rename).
 * The renamed-away legacy column is left in place (harmless, unused) until
 * the final cleanup phase drops the legacy tables entirely.
 */
return new class extends Migration
{
    /**
     * @var list<array{0: string, 1: string}> [table, column]
     */
    private const COLUMNS = [
        ['circle_teacher', 'teacher_id'],
        ['attendances', 'teacher_id'],
        ['attendances', 'student_id'],
        ['student_plans', 'teacher_id'],
        ['student_plans', 'student_id'],
        ['turn_reservation_sessions', 'teacher_id'],
        ['turn_reservations', 'student_id'],
        ['gamification_team_task_assignments', 'teacher_id'],
        ['leaderboard_scores', 'student_id'],
        ['leaderboard_extra_points', 'student_id'],
        ['challenges', 'student_id'],
        ['challenges', 'guardian_id'],
        ['student_status_histories', 'student_id'],
        ['student_exams', 'student_id'],
        ['student_ode_plans', 'student_id'],
        ['student_hadith_plans', 'student_id'],
        ['form_responses', 'student_id'],
        ['guardian_notifications', 'student_id'],
        ['guardian_notifications', 'guardian_id'],
        ['gamification_tracks', 'student_id'],
        ['gamification_student_states', 'student_id'],
        ['gamification_team_student', 'student_id'],
        ['gamification_transactions', 'student_id'],
        ['gamification_store_purchases', 'student_id'],
        ['gamification_purchase_votes', 'student_id'],
        ['gamification_badge_student', 'student_id'],
        ['gamification_claimed_milestones', 'student_id'],
        ['stage_supervisor', 'supervisor_id'],
        ['leaderboards', 'supervisor_id'],
        ['forms', 'supervisor_id'],
        ['disabled_role_pages', 'disabled_by'],
        ['roles', 'created_by'],
        ['role_screen_permissions', 'enabled_by'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            $newColumn = "{$column}_v2";
            $legacyColumn = "{$column}_legacy";

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $newColumn)) {
                continue;
            }

            DB::statement("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$column}\" TO \"{$legacyColumn}\"");
            DB::statement("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$newColumn}\" TO \"{$column}\"");
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::COLUMNS) as [$table, $column]) {
            $newColumn = "{$column}_v2";
            $legacyColumn = "{$column}_legacy";

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $legacyColumn)) {
                continue;
            }

            DB::statement("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$column}\" TO \"{$newColumn}\"");
            DB::statement("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$legacyColumn}\" TO \"{$column}\"");
        }
    }
};
