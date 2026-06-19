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
        // Add teacher_id to gamification_team_task_assignments
        Schema::table('gamification_team_task_assignments', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
        });

        // Create team task criteria table
        Schema::create('gamification_team_task_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_task_id')->constrained('gamification_team_tasks')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Create assignment scores table
        Schema::create('gamification_team_task_assignment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('gamification_team_task_assignments')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('gamification_team_task_criteria')->cascadeOnDelete();
            $table->integer('score')->nullable(); // score out of 10
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamification_team_task_assignment_scores');
        Schema::dropIfExists('gamification_team_task_criteria');

        Schema::table('gamification_team_task_assignments', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn('teacher_id');
        });
    }
};
