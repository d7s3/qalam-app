<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a student actually did, on a day, against one track.
     *
     * `source` separates what the student confirmed himself from what was
     * written for him when his teacher recorded a recitation. Without it a
     * report cannot tell the two apart, and a teacher revising a grade would
     * have no way to correct the entry it produced.
     */
    public function up(): void
    {
        Schema::create('student_self_program_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('self_program_item_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->decimal('amount_done', 8, 2)->default(0);
            $table->string('source')->default('student');
            $table->timestamps();

            $table->unique(['student_id', 'self_program_item_id', 'entry_date', 'source'], 'student_self_program_entries_unique');
            $table->index(['student_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_self_program_entries');
    }
};
