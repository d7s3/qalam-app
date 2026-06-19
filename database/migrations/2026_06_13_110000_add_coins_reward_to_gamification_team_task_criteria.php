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
        Schema::table('gamification_team_task_criteria', function (Blueprint $table) {
            $table->integer('coins_reward')->default(0)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_team_task_criteria', function (Blueprint $table) {
            $table->dropColumn('coins_reward');
        });
    }
};
