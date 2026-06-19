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
        Schema::table('gamification_badges', function (Blueprint $table) {
            $table->foreignId('leaderboard_criterion_id')
                ->nullable()
                ->after('badge_type')
                ->constrained('leaderboard_criteria')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_badges', function (Blueprint $table) {
            $table->dropForeign(['leaderboard_criterion_id']);
            $table->dropColumn('leaderboard_criterion_id');
        });
    }
};
