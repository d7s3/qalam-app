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
        Schema::create('gamification_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained('leaderboards')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('gamification_activity_ranks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('gamification_activities')->onDelete('cascade');
            $table->string('name');
            $table->integer('team_xp')->default(0);
            $table->integer('team_coins')->default(0);
            $table->integer('member_xp')->default(0);
            $table->integer('member_coins')->default(0);
            $table->timestamps();
        });

        Schema::create('gamification_activity_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('gamification_activities')->onDelete('cascade');
            $table->string('name');
            $table->date('round_date');
            $table->timestamps();
        });

        Schema::create('gamification_activity_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('gamification_activity_rounds')->onDelete('cascade');
            $table->foreignId('rank_id')->constrained('gamification_activity_ranks')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('gamification_teams')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamification_activity_winners');
        Schema::dropIfExists('gamification_activity_rounds');
        Schema::dropIfExists('gamification_activity_ranks');
        Schema::dropIfExists('gamification_activities');
    }
};
