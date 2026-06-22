<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_hadith_plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_hadith_plan_id')->constrained('student_hadith_plans')->onDelete('cascade');
            $table->date('date');
            $table->string('day_name');
            $table->integer('from_line_number');
            $table->integer('to_line_number');
            $table->integer('review_from_line_number')->nullable();
            $table->integer('review_to_line_number')->nullable();
            $table->integer('hifz_achievement')->nullable(); // 1 = acceptable, 2 = good, 3 = excellent
            $table->integer('review_achievement')->nullable();
            $table->dateTime('hifz_graded_at')->nullable();
            $table->dateTime('review_graded_at')->nullable();
            $table->timestamps();

            // Index for sorting and matching plan days by date
            $table->index(['student_hadith_plan_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_hadith_plan_days');
    }
};
