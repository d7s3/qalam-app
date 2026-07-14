<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes a mistake in the Phase 3/4 backfill: the mapping list used
 * `gamification_tracks` (the tracks catalog table, which has no student_id
 * column at all) instead of `gamification_track_student` (the actual pivot
 * table with a student_id FK). This adds, backfills, and swaps that one
 * column in a single migration since it's a single missed table, not a
 * new phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gamification_track_student') || ! Schema::hasColumn('gamification_track_student', 'student_id')) {
            return;
        }

        Schema::table('gamification_track_student', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id_v2')->nullable();
        });

        DB::statement('
            UPDATE "gamification_track_student"
            SET "student_id_v2" = (
                SELECT new_id FROM id_migration_map
                WHERE old_table = ? AND old_id = "gamification_track_student"."student_id"
            )
            WHERE "student_id" IS NOT NULL
        ', ['students']);

        DB::statement('ALTER TABLE "gamification_track_student" RENAME COLUMN "student_id" TO "student_id_legacy"');
        DB::statement('ALTER TABLE "gamification_track_student" RENAME COLUMN "student_id_v2" TO "student_id"');
    }

    public function down(): void
    {
        if (! Schema::hasTable('gamification_track_student') || ! Schema::hasColumn('gamification_track_student', 'student_id_legacy')) {
            return;
        }

        DB::statement('ALTER TABLE "gamification_track_student" RENAME COLUMN "student_id" TO "student_id_v2"');
        DB::statement('ALTER TABLE "gamification_track_student" RENAME COLUMN "student_id_legacy" TO "student_id"');
    }
};
