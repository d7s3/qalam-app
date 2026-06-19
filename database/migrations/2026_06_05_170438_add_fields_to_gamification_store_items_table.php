<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gamification_store_items', function (Blueprint $table) {
            $table->boolean('is_team_product')->default(false)->after('item_type');
            $table->boolean('is_streak_freeze')->default(false)->after('is_team_product');
        });

        // Update existing records to match previous implicit rules:
        // shield, team_points, team_attack were team products.
        DB::table('gamification_store_items')
            ->whereIn('item_type', ['shield', 'team_points', 'team_attack'])
            ->update(['is_team_product' => true]);

        // If it was Streak Freeze by name, mark it as is_streak_freeze
        DB::table('gamification_store_items')
            ->where('name', 'تجميد الحماسة')
            ->orWhere('name', 'Streak Freeze')
            ->update(['is_streak_freeze' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_store_items', function (Blueprint $table) {
            $table->dropColumn(['is_team_product', 'is_streak_freeze']);
        });
    }
};
