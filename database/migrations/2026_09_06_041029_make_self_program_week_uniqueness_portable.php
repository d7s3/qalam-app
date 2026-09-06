<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One index that guards the week number on every database.
     *
     * The partial index written a moment ago holds only on SQLite and Postgres.
     * On MySQL it is not created at all, and nothing says so — so an academy
     * deploying onto MySQL, which is the ordinary choice for a server, would
     * have had the programme-wide weeks silently unguarded.
     *
     * A generated column carries the same idea without a NULL in it: zero where
     * no cohort is named. Being a real value, it takes part in a plain unique
     * index, and SQL has nothing to be clever about.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('drop index if exists self_program_weeks_programme_unique');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists self_program_weeks_programme_unique');
        }

        Schema::table('self_program_weeks', function (Blueprint $table) {
            $table->dropUnique('self_program_weeks_scope_unique');

            // Virtual rather than stored: SQLite refuses to add a stored
            // generated column to a table that already exists, and both it and
            // MySQL index a virtual one perfectly well.
            $table->unsignedBigInteger('cohort_key')->virtualAs('coalesce(circle_id, 0)');

            $table->unique(['stage_id', 'cohort_key', 'program_type', 'week_number'], 'self_program_weeks_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('self_program_weeks', function (Blueprint $table) {
            $table->dropUnique('self_program_weeks_scope_unique');
            $table->dropColumn('cohort_key');
            $table->unique(['stage_id', 'circle_id', 'program_type', 'week_number'], 'self_program_weeks_scope_unique');
        });
    }
};
