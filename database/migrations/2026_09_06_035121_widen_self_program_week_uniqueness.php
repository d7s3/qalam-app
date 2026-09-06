<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A week number is unique within what it was written for.
     *
     * It used to be unique within the programme alone, which was right while
     * the self programme was only ever written for a programme. A teacher
     * writes for his cohort now, and his first week collided with the
     * supervisor's first week in the same programme.
     *
     * Two indexes rather than one, because a nullable column in a unique index
     * never rejects anything: SQL counts two NULLs as different, so the
     * programme-wide weeks — the ones naming no cohort — would have been left
     * unguarded by a four-column index. The second index covers exactly those,
     * and is partial, which SQLite and Postgres both support.
     */
    public function up(): void
    {
        Schema::table('self_program_weeks', function (Blueprint $table) {
            $table->dropUnique('self_program_weeks_stage_unique');
            $table->unique(['stage_id', 'circle_id', 'program_type', 'week_number'], 'self_program_weeks_scope_unique');
        });

        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'create unique index self_program_weeks_programme_unique
                 on self_program_weeks (stage_id, program_type, week_number)
                 where circle_id is null'
            );
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('drop index if exists self_program_weeks_programme_unique');
        }

        Schema::table('self_program_weeks', function (Blueprint $table) {
            $table->dropUnique('self_program_weeks_scope_unique');
            $table->unique(['stage_id', 'program_type', 'week_number'], 'self_program_weeks_stage_unique');
        });
    }
};
