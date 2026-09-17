<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who wrote this entry.
 *
 * On «قرآني إجمالي» the wird is written by the student or by his teacher,
 * whichever of them is at hand. Both write the same row — the table's unique
 * index is (student, field, day, source), and two sources on one day would be
 * two rows that add up, so the same wird would be counted twice.
 *
 * One row it is, then; and because the row no longer says by itself whose hand
 * wrote it, it is asked to carry the name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_self_program_entries', function (Blueprint $table) {
            $table->nullableMorphs('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('student_self_program_entries', function (Blueprint $table) {
            $table->dropMorphs('recorded_by');
        });
    }
};
