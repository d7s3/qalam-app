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
        Schema::create('gamification_news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // level_up, badge, activity_win, team_attack, team_attack_blocked, adjustment, team_task
            $table->date('event_date');
            $table->json('data');
            $table->timestamps();

            $table->index(['leaderboard_id', 'event_date']);
        });

        // Tracks the highest level a student has already been announced for, so a
        // level-up news item is recorded only when they cross to a new level.
        Schema::table('gamification_student_states', function (Blueprint $table) {
            $table->unsignedInteger('notified_level')->nullable()->after('streak_freezes_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_student_states', function (Blueprint $table) {
            $table->dropColumn('notified_level');
        });
        Schema::dropIfExists('gamification_news');
    }
};
