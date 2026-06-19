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
        Schema::table('gamification_store_items', function (Blueprint $table) {
            $table->boolean('require_assistant_approval')->default(false)->after('is_streak_freeze');
            $table->integer('require_member_approval_count')->default(0)->after('require_assistant_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_store_items', function (Blueprint $table) {
            $table->dropColumn(['require_assistant_approval', 'require_member_approval_count']);
        });
    }
};
