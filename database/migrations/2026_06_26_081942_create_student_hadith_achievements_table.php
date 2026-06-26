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
        Schema::create('student_hadith_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_hadith_plan_id')->constrained('student_hadith_plans')->onDelete('cascade');
            $table->foreignId('hadith_path_day_id')->constrained('hadith_path_days')->onDelete('cascade');
            $table->integer('hifz_achievement')->nullable(); // 1 = acceptable, 2 = good, 3 = excellent
            $table->integer('review_achievement')->nullable();
            $table->dateTime('hifz_graded_at')->nullable();
            $table->dateTime('review_graded_at')->nullable();
            $table->timestamps();

            // Unique: a student can only have one achievement per path day
            $table->unique(['student_hadith_plan_id', 'hadith_path_day_id'], 'student_path_day_unique');
            // Index for fast lookup by plan
            $table->index('student_hadith_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_hadith_achievements');
    }
};
