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
        Schema::table('gamification_store_purchases', function (Blueprint $table) {
            $table->foreignId('target_team_id')->nullable()->after('team_id')->constrained('gamification_teams')->nullOnDelete();
            $table->date('target_date')->nullable()->after('target_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_store_purchases', function (Blueprint $table) {
            $table->dropForeign(['target_team_id']);
            $table->dropColumn(['target_team_id', 'target_date']);
        });
    }
};
