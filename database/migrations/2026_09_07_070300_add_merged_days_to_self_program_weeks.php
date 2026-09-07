<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Days the week treats as one.
     *
     * A weekend, or two days the academy runs together, or the three of a trip:
     * the student is asked for one amount across them rather than a share of
     * each, and the grid shows one column where there were three.
     *
     * Kept on the week rather than in a table of its own because the week is
     * already scoped by what it was written for — the supervisor's belongs to
     * the programme and the teacher's to his cohort, and a merge belongs to
     * whichever week it was drawn on.
     */
    public function up(): void
    {
        Schema::table('self_program_weeks', function (Blueprint $table) {
            // [["2026-09-10","2026-09-11"], ...] — each inner array is one column.
            $table->json('merged_days')->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('self_program_weeks', function (Blueprint $table) {
            $table->dropColumn('merged_days');
        });
    }
};
