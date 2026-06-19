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
        // 1. Modifying leaderboards table
        Schema::table('leaderboards', function (Blueprint $table) {
            $table->string('competition_type')->default('normal')->after('id');
            $table->string('theme_key')->nullable()->after('competition_type');
        });

        // 2. Modifying leaderboard_criteria table
        Schema::table('leaderboard_criteria', function (Blueprint $table) {
            $table->boolean('is_enthusiasm_trigger')->default(false)->after('points');
        });

        // 3. Creating gamification_student_states table
        Schema::create('gamification_student_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->integer('coins')->default(0);
            $table->integer('current_streak')->default(0);
            $table->integer('max_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->integer('streak_freezes_count')->default(0);
            $table->timestamps();

            $table->unique(['leaderboard_id', 'student_id']);
        });

        // 4. Creating gamification_teams table
        Schema::create('gamification_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('coins')->default(0); // Team Treasury
            $table->timestamp('shield_active_until')->nullable();
            $table->timestamp('multiplier_active_until')->nullable();
            $table->timestamps();
        });

        // 5. Creating gamification_team_student pivot table
        Schema::create('gamification_team_student', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained('gamification_teams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // leader, assistant, member
            $table->primary(['team_id', 'student_id']);
        });

        // 6. Creating gamification_transactions table
        Schema::create('gamification_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('gamification_teams')->cascadeOnDelete();
            $table->string('type'); // earn, spend, donate, refund
            $table->integer('amount'); // Positive or negative
            $table->string('description');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
        });

        // 7. Creating gamification_store_items table
        Schema::create('gamification_store_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('price');
            $table->string('item_type'); // team_points, team_attack, multiplier, shield, custom
            $table->integer('value')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 8. Creating gamification_store_purchases table
        Schema::create('gamification_store_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_item_id')->constrained('gamification_store_items')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('gamification_teams')->cascadeOnDelete();
            $table->string('status')->default('pending_approval'); // pending_approval, approved, rejected, completed
            $table->integer('price_paid');
            $table->timestamps();
        });

        // 9. Creating gamification_purchase_votes table
        Schema::create('gamification_purchase_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_purchase_id')->constrained('gamification_store_purchases')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->boolean('vote'); // true = approve, false = reject
            $table->timestamps();
        });

        // 10. Creating gamification_badges table
        Schema::create('gamification_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon');
            $table->string('badge_type'); // hifz_streak, attendance_streak, custom
            $table->integer('requirement_value')->default(0);
            $table->timestamps();
        });

        // 11. Creating gamification_badge_student table
        Schema::create('gamification_badge_student', function (Blueprint $table) {
            $table->foreignId('badge_id')->constrained('gamification_badges')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['badge_id', 'student_id']);
        });

        // 12. Creating gamification_streak_milestones table
        Schema::create('gamification_streak_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->integer('days_required');
            $table->integer('reward_xp')->default(0);
            $table->integer('reward_coins')->default(0);
            $table->foreignId('reward_badge_id')->nullable()->constrained('gamification_badges')->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 13. Creating gamification_claimed_milestones table
        Schema::create('gamification_claimed_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained('gamification_streak_milestones')->cascadeOnDelete();
            $table->integer('streak_run_count'); // Streak number at the time of claim
            $table->timestamps();
        });

        // 14. Creating gamification_levels table
        Schema::create('gamification_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->integer('level_number');
            $table->string('name');
            $table->integer('xp_required');
            $table->string('icon')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamification_levels');
        Schema::dropIfExists('gamification_claimed_milestones');
        Schema::dropIfExists('gamification_streak_milestones');
        Schema::dropIfExists('gamification_badge_student');
        Schema::dropIfExists('gamification_badges');
        Schema::dropIfExists('gamification_purchase_votes');
        Schema::dropIfExists('gamification_store_purchases');
        Schema::dropIfExists('gamification_store_items');
        Schema::dropIfExists('gamification_transactions');
        Schema::dropIfExists('gamification_team_student');
        Schema::dropIfExists('gamification_teams');
        Schema::dropIfExists('gamification_student_states');

        Schema::table('leaderboard_criteria', function (Blueprint $table) {
            $table->dropColumn('is_enthusiasm_trigger');
        });

        Schema::table('leaderboards', function (Blueprint $table) {
            $table->dropColumn(['competition_type', 'theme_key']);
        });
    }
};
