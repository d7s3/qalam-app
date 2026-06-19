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
        Schema::create('student_ode_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('ode_id')->constrained('odes')->onDelete('cascade');
            $table->date('start_date');
            $table->string('status')->default('active'); // active, completed, suspended
            $table->string('created_by_role'); // supervisor, teacher
            $table->timestamps();

            // Indexes for fast lookup of active plans for students
            $table->index(['student_id', 'status']);
        });

        Schema::create('student_ode_plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_ode_plan_id')->constrained('student_ode_plans')->onDelete('cascade');
            $table->date('date');
            $table->string('day_name');
            $table->integer('from_verse_number');
            $table->integer('to_verse_number');
            $table->integer('review_from_verse_number')->nullable();
            $table->integer('review_to_verse_number')->nullable();
            $table->integer('hifz_achievement')->nullable(); // 1 = acceptable, 2 = good, 3 = excellent
            $table->integer('review_achievement')->nullable();
            $table->dateTime('hifz_graded_at')->nullable();
            $table->dateTime('review_graded_at')->nullable();
            $table->timestamps();

            // Index for sorting and matching plan days by date
            $table->index(['student_ode_plan_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_ode_plan_days');
        Schema::dropIfExists('student_ode_plans');
    }
};
