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
        Schema::create('student_ode_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_ode_plan_id')->constrained('student_ode_plans')->onDelete('cascade');
            $table->foreignId('ode_path_day_id')->constrained('ode_path_days')->onDelete('cascade');
            $table->integer('hifz_achievement')->nullable(); // 1 = acceptable, 2 = good, 3 = excellent
            $table->integer('review_achievement')->nullable();
            $table->dateTime('hifz_graded_at')->nullable();
            $table->dateTime('review_graded_at')->nullable();
            $table->timestamps();

            // Unique: a student can only have one achievement per path day
            $table->unique(['student_ode_plan_id', 'ode_path_day_id'], 'student_ode_path_day_unique');
            // Index for fast lookup by plan
            $table->index('student_ode_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_ode_achievements');
    }
};
