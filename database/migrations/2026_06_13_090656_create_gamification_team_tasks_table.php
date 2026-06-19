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
        Schema::create('gamification_team_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('evaluation_criteria')->nullable();
            $table->integer('xp_reward')->default(0);
            $table->integer('coins_reward')->default(0);
            $table->timestamps();
        });

        Schema::create('gamification_team_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_task_id')->constrained('gamification_team_tasks')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('gamification_teams')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('assigned'); // assigned, completed
            $table->integer('grade')->nullable(); // score out of 100
            $table->text('notes')->nullable(); // evaluation notes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamification_team_task_assignments');
        Schema::dropIfExists('gamification_team_tasks');
    }
};
