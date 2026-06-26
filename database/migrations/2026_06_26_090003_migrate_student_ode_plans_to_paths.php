<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Moves student ode plans onto the shared-path model: plans now reference an
     * ode_path instead of an ode directly, and the per-student day schedule
     * (student_ode_plan_days) is replaced by shared ode_path_days + achievements.
     */
    public function up(): void
    {
        Schema::dropIfExists('student_ode_plan_days');
        Schema::dropIfExists('student_ode_plans');

        Schema::create('student_ode_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('ode_path_id')->constrained('ode_paths')->onDelete('cascade');
            $table->date('start_date');
            $table->string('status')->default('active'); // active, completed, suspended
            $table->string('created_by_role'); // supervisor, teacher
            $table->timestamps();

            // Indexes for fast lookup of active plans for students
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_ode_plans');

        Schema::create('student_ode_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('ode_id')->constrained('odes')->onDelete('cascade');
            $table->date('start_date');
            $table->string('status')->default('active');
            $table->string('created_by_role');
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }
};
