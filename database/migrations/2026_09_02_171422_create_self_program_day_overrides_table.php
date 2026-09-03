<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A teacher's edit to the suggested daily split — and only that.
     *
     * The ordinary split is not stored: it follows from the week's amount and
     * the stage's working days, so it is computed on the spot. Storing it would
     * mean five tracks over fifty-two weeks across every working day of every
     * circle and student, millions of rows restating a formula. What cannot be
     * derived is a teacher overruling it, which is what this table holds.
     *
     * A row naming only a circle speaks for all its students; one naming a
     * student speaks for that student alone and wins over the circle's.
     */
    public function up(): void
    {
        Schema::create('self_program_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_program_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->date('day_date');
            $table->decimal('amount', 8, 2);
            $table->timestamps();

            $table->unique(['self_program_item_id', 'circle_id', 'student_id', 'day_date'], 'self_program_day_overrides_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_program_day_overrides');
    }
};
