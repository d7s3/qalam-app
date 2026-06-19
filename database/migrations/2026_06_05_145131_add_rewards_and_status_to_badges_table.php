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
            $table->integer('reward_xp')->default(0)->after('requirement_value');
            $table->integer('reward_coins')->default(0)->after('reward_xp');
        });

        Schema::table('gamification_badge_student', function (Blueprint $table) {
            $table->string('status')->default('pending_approval')->after('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_badge_student', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('gamification_badges', function (Blueprint $table) {
            $table->dropColumn(['reward_xp', 'reward_coins']);
        });
    }
};
