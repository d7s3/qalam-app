<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The columns renamed to "*_legacy" by the Phase 4 swap migration were
 * originally NOT NULL (created via ->constrained() without ->nullable()).
 * Since nothing populates them going forward, they must be relaxed to
 * nullable or every future INSERT into these tables fails with a NOT NULL
 * constraint violation. Uses ->change(), which Laravel implements as a full
 * table rebuild under the hood for SQLite (unlike plain rename/drop).
 */
return new class extends Migration
{
    /**
     * @var list<array{0: string, 1: string}> [table, legacy_column]
     */
    private const COLUMNS = [
        ['circle_teacher', 'teacher_id_legacy'],
        ['attendances', 'teacher_id_legacy'],
        ['attendances', 'student_id_legacy'],
        ['student_plans', 'teacher_id_legacy'],
        ['student_plans', 'student_id_legacy'],
        ['turn_reservation_sessions', 'teacher_id_legacy'],
        ['turn_reservations', 'student_id_legacy'],
        ['gamification_team_task_assignments', 'teacher_id_legacy'],
        ['leaderboard_scores', 'student_id_legacy'],
        ['leaderboard_extra_points', 'student_id_legacy'],
        ['challenges', 'student_id_legacy'],
        ['challenges', 'guardian_id_legacy'],
        ['student_status_histories', 'student_id_legacy'],
        ['student_exams', 'student_id_legacy'],
        ['student_ode_plans', 'student_id_legacy'],
        ['student_hadith_plans', 'student_id_legacy'],
        ['form_responses', 'student_id_legacy'],
        ['guardian_notifications', 'student_id_legacy'],
        ['guardian_notifications', 'guardian_id_legacy'],
        ['gamification_track_student', 'student_id_legacy'],
        ['gamification_student_states', 'student_id_legacy'],
        ['gamification_team_student', 'student_id_legacy'],
        ['gamification_transactions', 'student_id_legacy'],
        ['gamification_store_purchases', 'student_id_legacy'],
        ['gamification_purchase_votes', 'student_id_legacy'],
        ['gamification_badge_student', 'student_id_legacy'],
        ['gamification_claimed_milestones', 'student_id_legacy'],
        ['stage_supervisor', 'supervisor_id_legacy'],
        ['leaderboards', 'supervisor_id_legacy'],
        ['forms', 'supervisor_id_legacy'],
        ['disabled_role_pages', 'disabled_by_legacy'],
        ['roles', 'created_by_legacy'],
        ['role_screen_permissions', 'enabled_by_legacy'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $legacyColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $legacyColumn)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($legacyColumn) {
                $blueprint->unsignedBigInteger($legacyColumn)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Not reversible in a meaningful way — these columns are dead weight
        // kept only for audit purposes until the final cleanup phase.
    }
};
