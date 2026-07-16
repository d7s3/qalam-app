<?php

use App\Console\Commands\MigrateLegacyUsers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (`users:migrate-legacy`) was a manual command, while Phases 3/4 are
 * migrations. Deploys run migrations automatically but never run commands, so
 * any environment that migrated before someone remembered the command ended
 * up with `users`/`id_migration_map` empty and every swapped FK column NULL
 * (the original ids preserved in the "_legacy" twin column).
 *
 * This migration makes the ordering fool-proof: it runs the idempotent Phase 2
 * command itself, then re-backfills every swapped FK column that is still
 * NULL from its "_legacy" twin through `id_migration_map`. On environments
 * where the command already ran before Phase 3, both steps are no-ops.
 */
return new class extends Migration
{
    /**
     * Phase 4's swapped columns plus `gamification_track_student` (swapped by
     * the later fix migration), each with its legacy source table.
     *
     * @var list<array{0: string, 1: string, 2: string}> [table, column, source_role_table]
     */
    private const MAPPINGS = [
        ['circle_teacher', 'teacher_id', 'teachers'],
        ['attendances', 'teacher_id', 'teachers'],
        ['attendances', 'student_id', 'students'],
        ['student_plans', 'teacher_id', 'teachers'],
        ['student_plans', 'student_id', 'students'],
        ['turn_reservation_sessions', 'teacher_id', 'teachers'],
        ['turn_reservations', 'student_id', 'students'],
        ['gamification_team_task_assignments', 'teacher_id', 'teachers'],
        ['leaderboard_scores', 'student_id', 'students'],
        ['leaderboard_extra_points', 'student_id', 'students'],
        ['challenges', 'student_id', 'students'],
        ['challenges', 'guardian_id', 'guardians'],
        ['student_status_histories', 'student_id', 'students'],
        ['student_exams', 'student_id', 'students'],
        ['student_ode_plans', 'student_id', 'students'],
        ['student_hadith_plans', 'student_id', 'students'],
        ['form_responses', 'student_id', 'students'],
        ['guardian_notifications', 'student_id', 'students'],
        ['guardian_notifications', 'guardian_id', 'guardians'],
        ['gamification_track_student', 'student_id', 'students'],
        ['gamification_student_states', 'student_id', 'students'],
        ['gamification_team_student', 'student_id', 'students'],
        ['gamification_transactions', 'student_id', 'students'],
        ['gamification_store_purchases', 'student_id', 'students'],
        ['gamification_purchase_votes', 'student_id', 'students'],
        ['gamification_badge_student', 'student_id', 'students'],
        ['gamification_claimed_milestones', 'student_id', 'students'],
        ['stage_supervisor', 'supervisor_id', 'supervisors'],
        ['leaderboards', 'supervisor_id', 'supervisors'],
        ['forms', 'supervisor_id', 'supervisors'],
        ['disabled_role_pages', 'disabled_by', 'managers'],
        ['roles', 'created_by', 'managers'],
        ['role_screen_permissions', 'enabled_by', 'managers'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('id_migration_map')) {
            return;
        }

        if (class_exists(MigrateLegacyUsers::class)) {
            Artisan::call('users:migrate-legacy');
        }

        foreach (self::MAPPINGS as [$table, $column, $sourceTable]) {
            $legacyColumn = "{$column}_legacy";

            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, $column)
                || ! Schema::hasColumn($table, $legacyColumn)) {
                continue;
            }

            DB::statement("
                UPDATE \"{$table}\"
                SET \"{$column}\" = (
                    SELECT new_id FROM id_migration_map
                    WHERE old_table = ? AND old_id = \"{$table}\".\"{$legacyColumn}\"
                )
                WHERE \"{$column}\" IS NULL AND \"{$legacyColumn}\" IS NOT NULL
            ", [$sourceTable]);
        }
    }

    /**
     * Data repair only — nothing to reverse. The copied users/user_roles rows
     * are left in place; re-running up() is safe at any time.
     */
    public function down(): void
    {
        //
    }
};
