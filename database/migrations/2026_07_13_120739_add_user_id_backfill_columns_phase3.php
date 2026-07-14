<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 of the 6-tables-into-one consolidation: purely additive, for
 * every dependent table found to hold a hard FK into one of the 6 legacy
 * role tables. For each, adds a NEW nullable "{column}_v2" column pointing
 * at the same value translated through `id_migration_map`, and backfills
 * it. The ORIGINAL column is left completely untouched — the app keeps
 * reading/writing it unchanged. A later cutover phase drops the old column
 * and renames "_v2" into its place.
 *
 * students.guardian_id is NOT in this list — that FK was already resolved
 * directly on the `users` table itself during Phase 2's data copy.
 */
return new class extends Migration
{
    /**
     * @var list<array{0: string, 1: string, 2: string}> [table, old_column, source_role_table]
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
        ['gamification_tracks', 'student_id', 'students'],
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

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::MAPPINGS as [$table, $column, $sourceTable]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $newColumn = "{$column}_v2";

            if (! Schema::hasColumn($table, $newColumn)) {
                Schema::table($table, function (Blueprint $blueprint) use ($newColumn) {
                    $blueprint->unsignedBigInteger($newColumn)->nullable();
                });
            }

            DB::statement("
                UPDATE \"{$table}\"
                SET \"{$newColumn}\" = (
                    SELECT new_id FROM id_migration_map
                    WHERE old_table = ? AND old_id = \"{$table}\".\"{$column}\"
                )
                WHERE \"{$column}\" IS NOT NULL
            ", [$sourceTable]);

            Schema::table($table, function (Blueprint $blueprint) use ($newColumn) {
                $blueprint->index($newColumn);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::MAPPINGS as [$table, $column, $sourceTable]) {
            $newColumn = "{$column}_v2";

            if (Schema::hasTable($table) && Schema::hasColumn($table, $newColumn)) {
                Schema::table($table, function (Blueprint $blueprint) use ($newColumn) {
                    $blueprint->dropColumn($newColumn);
                });
            }
        }
    }
};
