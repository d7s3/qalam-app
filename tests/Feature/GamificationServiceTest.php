<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\GamificationBadge;
use App\Models\GamificationLevel;
use App\Models\GamificationNews;
use App\Models\GamificationStoreItem;
use App\Models\GamificationStorePurchase;
use App\Models\GamificationStudentState;
use App\Models\GamificationTeam;
use App\Models\GamificationTrack;
use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\Ode;
use App\Models\OdePath;
use App\Models\OdePathDay;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentOdeAchievement;
use App\Models\StudentOdePlan;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Teacher;
use App\Services\GamificationNewsService;
use App\Services\GamificationService;
use App\Services\LeaderboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-06-10 10:00:00');
    $this->stage = Stage::create(['name' => 'المرحلة الأولى']);
    $this->circle = Circle::create(['name' => 'الحلقة الأولى', 'stage_id' => $this->stage->id]);

    $this->teacher = Teacher::create([
        'name' => 'أحمد المعلم',
        'email' => 'teacher@example.com',
        'password' => bcrypt('password'),
        'status' => 'active',
        'is_approved' => true,
    ]);

    $this->student = Student::create([
        'name' => 'أحمد علي',
        'email' => 'ahmed@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    // Active Gamification Competition
    $this->leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الفضاء الكبرى',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(10),
        'end_date' => now()->addDays(10),
        'is_active' => true,
        'settings' => [
            'hifz_enabled' => true,
            'hifz_excellent' => 10,
            'hifz_good' => 7,
            'hifz_acceptable' => 4,
            'review_enabled' => true,
            'review_excellent' => 5,
            'review_good' => 3,
            'attendance_enabled' => true,
            'attendance_present' => 4,
            'attendance_late' => 2,
            'enthusiasm_enabled' => true,
            'enthusiasm_type' => 'both',
            'enthusiasm_min_grade' => 2,
        ],
    ]);

    // Sync circle participation
    $this->leaderboard->circles()->attach($this->circle->id);
});

it('finds active gamification leaderboards', function () {
    $active = GamificationService::getActiveLeaderboards($this->student);
    expect($active)->toHaveCount(1);
    expect($active->first()->id)->toBe($this->leaderboard->id);
});

it('syncs student hifz and review points', function () {
    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'plan_type' => 'hifz_review',
        'start_date' => now()->subDays(5),
        'is_approved' => 1,
        'days_count' => 30,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $day = StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => now()->format('Y-m-d'),
        'day_name' => 'الجمعة',
        'hifz_achievement' => 3, // Excellent
        'review_achievement' => 2, // Good
        'hifz_graded_at' => now(),
        'review_graded_at' => now(),
    ]);

    GamificationService::syncStudentPlanDayXP($day);

    $transactions = GamificationTransaction::where('student_id', $this->student->id)->get();
    expect($transactions)->toHaveCount(1);

    // excellent hifz = 10, good review = 3 => total = 13
    expect($transactions->first()->amount)->toBe(13);

    $state = GamificationStudentState::where('student_id', $this->student->id)->first();
    expect($state->coins)->toBe(13);
});

it('holds rewards as pending until claimed when manual claim is enabled', function () {
    $settings = $this->leaderboard->settings;
    $settings['manual_claim_enabled'] = true;
    $this->leaderboard->update(['settings' => $settings]);

    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'plan_type' => 'hifz_review',
        'start_date' => now()->subDays(5),
        'is_approved' => 1,
        'days_count' => 30,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    $day = StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => now()->format('Y-m-d'),
        'day_name' => 'الجمعة',
        'hifz_achievement' => 3,
        'review_achievement' => 2,
        'hifz_graded_at' => now(),
        'review_graded_at' => now(),
    ]);
    GamificationService::syncStudentPlanDayXP($day);

    // Transaction exists but is pending → not counted yet
    $tx = GamificationTransaction::where('student_id', $this->student->id)->first();
    expect($tx->claimed_at)->toBeNull();
    expect(GamificationService::getStudentXP($this->student->id, $this->leaderboard->id))->toBe(0);
    expect(GamificationStudentState::where('student_id', $this->student->id)->first()->coins)->toBe(0);
    expect(GamificationService::getPendingRewards($this->student->id, $this->leaderboard->id))->toHaveCount(1);

    // Claim it → now counted
    expect(GamificationService::claimReward($tx->id, $this->student->id))->toBeTrue();
    expect($tx->fresh()->claimed_at)->not->toBeNull();
    expect(GamificationService::getStudentXP($this->student->id, $this->leaderboard->id))->toBe(13);
    expect(GamificationStudentState::where('student_id', $this->student->id)->first()->coins)->toBe(13);
    expect(GamificationService::getPendingRewards($this->student->id, $this->leaderboard->id))->toHaveCount(0);
});

it('claims all pending rewards at once and applies deductions immediately', function () {
    $settings = $this->leaderboard->settings;
    $settings['manual_claim_enabled'] = true;
    $this->leaderboard->update(['settings' => $settings]);

    // Two pending positive rewards (attendance + extra points)
    $attendance = Attendance::create([
        'student_id' => $this->student->id,
        'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacher->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);
    GamificationService::syncStudentAttendanceXP($attendance);

    $extraId = DB::table('leaderboard_extra_points')->insertGetId([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'date' => now()->format('Y-m-d'),
        'points' => 6,
        'notes' => 'مكافأة',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    GamificationService::syncStudentExtraPointsXP($extraId);

    // A deduction must NOT be pending — it applies immediately
    $deductId = DB::table('leaderboard_extra_points')->insertGetId([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'date' => now()->format('Y-m-d'),
        'points' => -2,
        'notes' => 'خصم',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    GamificationService::syncStudentExtraPointsXP($deductId);

    // Only the deduction counts so far: 0 claimed positives - 2 = clamped to 0 coins, XP -2
    expect(GamificationService::getPendingRewards($this->student->id, $this->leaderboard->id))->toHaveCount(2);
    expect(GamificationService::getStudentXP($this->student->id, $this->leaderboard->id))->toBe(-2);

    $count = GamificationService::claimAllRewards($this->student->id, $this->leaderboard->id);
    expect($count)->toBe(2);

    // present(4) + extra(6) - deduct(2) = 8
    expect(GamificationService::getStudentXP($this->student->id, $this->leaderboard->id))->toBe(8);
    expect(GamificationService::getPendingRewards($this->student->id, $this->leaderboard->id))->toHaveCount(0);
});

it('syncs student attendance points', function () {
    $attendance = Attendance::create([
        'student_id' => $this->student->id,
        'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacher->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);

    GamificationService::syncStudentAttendanceXP($attendance);

    $state = GamificationStudentState::where('student_id', $this->student->id)->first();
    // attendance present = 4
    expect($state->coins)->toBe(4);
});

it('manages streak and rewards milestones', function () {
    $badge = GamificationBadge::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'وسام المثابرة',
        'icon' => 'fire',
        'badge_type' => 'custom',
    ]);

    $milestone = DB::table('gamification_streak_milestones')->insertGetId([
        'leaderboard_id' => $this->leaderboard->id,
        'days_required' => 2,
        'reward_xp' => 10,
        'reward_coins' => 15,
        'reward_badge_id' => $badge->id,
        'description' => 'متتالية يومين',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Active streak triggers
    $state = GamificationStudentState::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 0,
        'current_streak' => 0,
        'max_streak' => 0,
        'last_activity_date' => null,
    ]);

    // Day 1: Student is present and grades are active
    $attendance1 = Attendance::create([
        'student_id' => $this->student->id,
        'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacher->id,
        'date' => now()->subDay()->format('Y-m-d'),
        'status' => 'present',
    ]);
    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'plan_type' => 'hifz_review',
        'start_date' => now()->subDays(5),
        'is_approved' => 1,
        'days_count' => 30,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    $day1 = StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => now()->subDay()->format('Y-m-d'),
        'day_name' => 'الخميس',
        'hifz_achievement' => 3,
        'hifz_graded_at' => now()->subDay(),
    ]);

    // This will trigger streak day 1
    GamificationService::syncStudentPlanDayXP($day1);
    GamificationService::syncStudentAttendanceXP($attendance1);

    $state->refresh();
    expect($state->current_streak)->toBe(1);
    expect($state->last_activity_date->format('Y-m-d'))->toBe(now()->subDay()->format('Y-m-d'));

    // Day 2: Consecutive day
    $attendance2 = Attendance::create([
        'student_id' => $this->student->id,
        'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacher->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);
    $day2 = StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => now()->format('Y-m-d'),
        'day_name' => 'الجمعة',
        'hifz_achievement' => 3,
        'hifz_graded_at' => now(),
    ]);

    // This triggers streak day 2 (Milestone reward!)
    GamificationService::syncStudentPlanDayXP($day2);
    GamificationService::syncStudentAttendanceXP($attendance2);

    $state->refresh();
    expect($state->current_streak)->toBe(2);
    expect($state->max_streak)->toBe(2);

    // Check milestone is claimed
    $claimed = DB::table('gamification_claimed_milestones')->where('student_id', $this->student->id)->first();
    expect($claimed)->not->toBeNull();
    expect($claimed->milestone_id)->toBe($milestone);

    // Check badge was awarded
    $hasBadge = DB::table('gamification_badge_student')->where('student_id', $this->student->id)->where('badge_id', $badge->id)->exists();
    expect($hasBadge)->toBeTrue();
});

it('protects streaks using streak freezes', function () {
    AcademicCalendarEvent::create([
        'event_name' => 'دوام كامل التجريبي',
        'start_date' => now()->subDays(15)->format('Y-m-d'),
        'end_date' => now()->addDays(15)->format('Y-m-d'),
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5, 6, 7], // All days are working days
        'is_visible' => true,
    ]);

    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'plan_type' => 'hifz_review',
        'start_date' => now()->subDays(10),
        'is_approved' => 1,
        'days_count' => 30,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    // Seed 5 days of enthusiasm in the past: from 7 days ago to 3 days ago
    for ($i = 7; $i >= 3; $i--) {
        Attendance::create([
            'student_id' => $this->student->id,
            'circle_id' => $this->circle->id,
            'teacher_id' => $this->teacher->id,
            'date' => now()->subDays($i)->format('Y-m-d'),
            'status' => 'present',
        ]);
        StudentPlanDay::create([
            'student_plan_id' => $plan->id,
            'date' => now()->subDays($i)->format('Y-m-d'),
            'day_name' => 'اليوم',
            'hifz_achievement' => 3,
            'hifz_graded_at' => now()->subDays($i),
        ]);
    }

    // Seed 2 approved purchases of streak freeze cards
    $freezeItem = GamificationStoreItem::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'بطاقة تجميد الحماسة',
        'price' => 10,
        'item_type' => 'custom',
        'is_streak_freeze' => true,
    ]);

    $targetDates = [
        now()->subDays(2)->format('Y-m-d'),
        now()->subDays(1)->format('Y-m-d'),
    ];
    for ($i = 0; $i < 2; $i++) {
        GamificationStorePurchase::create([
            'store_item_id' => $freezeItem->id,
            'student_id' => $this->student->id,
            'status' => 'approved',
            'price_paid' => 10,
            'created_at' => now()->subDays(4),
            'target_date' => $targetDates[$i],
        ]);
    }

    $state = GamificationStudentState::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 10,
        'current_streak' => 5,
        'max_streak' => 5,
        'last_activity_date' => now()->subDays(3), // 3 days ago activity
        'streak_freezes_count' => 2, // 2 freezes available
    ]);

    // Student has activity today
    $attendance = Attendance::create([
        'student_id' => $this->student->id,
        'circle_id' => $this->circle->id,
        'teacher_id' => $this->teacher->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);
    $day = StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => now()->format('Y-m-d'),
        'day_name' => 'الجمعة',
        'hifz_achievement' => 3,
        'hifz_graded_at' => now(),
    ]);

    // This will check if freeze bridges the 3 days gap
    // Gap = 3 days - 1 = 2 days missed. We have 2 freezes.
    GamificationService::syncStudentPlanDayXP($day);
    GamificationService::syncStudentAttendanceXP($attendance);

    $state->refresh();
    expect($state->streak_freezes_count)->toBe(2); // We purchased 2 freezes
    expect($state->current_streak)->toBe(8); // 5 + 3 days elapsed
    expect($state->last_activity_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
});

it('supports team donations', function () {
    // Balance earned on a PREVIOUS day so it counts as the start-of-day base.
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 400,
        'xp_amount' => 400,
        'description' => 'رصيد ابتدائي للاختبار',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    GamificationService::recalculateStudentState($this->student->id, $this->leaderboard->id);

    $team = GamificationTeam::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'أسرة مكة',
        'coins' => 50,
    ]);

    // Default daily limit is 10% of start-of-day coins (400) => 40 allowed.
    $result = GamificationService::donateCoinsToTeam($this->student->id, $team->id, 40);
    expect($result)->toBeTrue();

    $state = GamificationStudentState::where('student_id', $this->student->id)->where('leaderboard_id', $this->leaderboard->id)->first();
    $state->refresh();
    $team->refresh();

    expect($state->coins)->toBe(360); // 400 - 40
    expect($team->coins)->toBe(90); // 50 + 40

    // A donation moves coins only; it must NOT inflate the team's score.
    expect(GamificationService::getTeamScore($team, $this->leaderboard))->toBe(0);
});

it('caps daily donations at a percentage of the start-of-day coin balance', function () {
    // Level 1 allows donation with a 50% daily cap
    GamificationLevel::create([
        'leaderboard_id' => $this->leaderboard->id,
        'level_number' => 1,
        'name' => 'مبتدئ',
        'xp_required' => 0,
        'icon' => 'sparkles',
        'settings' => ['has_donation' => true, 'donation_max_limit' => 50],
    ]);

    $team = GamificationTeam::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'أسرة بدر',
        'coins' => 0,
    ]);

    // Start-of-day base: 100 coins earned yesterday
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 100,
        'xp_amount' => 100,
        'description' => 'رصيد سابق',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    // Today's earnings must NOT raise the base (start-of-day stays 100)
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 1000,
        'xp_amount' => 1000,
        'description' => 'كسب اليوم',
    ]);
    GamificationService::recalculateStudentState($this->student->id, $this->leaderboard->id);

    $status = GamificationService::getDailyDonationStatus($this->student->id, $this->leaderboard->id);
    expect($status['base'])->toBe(100);   // start-of-day only, ignores today's +1000
    expect($status['limit'])->toBe(50);   // 50% of 100
    expect($status['remaining'])->toBe(50);

    // Donate 30 (within the 50 cap)
    expect(GamificationService::donateCoinsToTeam($this->student->id, $team->id, 30))->toBeTrue();

    // 20 remaining: donating 25 must fail despite a large current balance
    $error = null;
    expect(GamificationService::donateCoinsToTeam($this->student->id, $team->id, 25, $error))->toBeFalse();
    expect($error)->toContain('المتبقي');

    // Donating the remaining 20 succeeds; then the daily cap is exhausted
    expect(GamificationService::donateCoinsToTeam($this->student->id, $team->id, 20))->toBeTrue();

    $status = GamificationService::getDailyDonationStatus($this->student->id, $this->leaderboard->id);
    expect($status['donated'])->toBe(50);
    expect($status['remaining'])->toBe(0);

    $error = null;
    expect(GamificationService::donateCoinsToTeam($this->student->id, $team->id, 1, $error))->toBeFalse();
    expect($error)->toContain('الأقصى');
});

it('blocks donations when the student level disallows them', function () {
    GamificationLevel::create([
        'leaderboard_id' => $this->leaderboard->id,
        'level_number' => 1,
        'name' => 'مبتدئ',
        'xp_required' => 0,
        'icon' => 'sparkles',
        'settings' => ['has_donation' => false, 'donation_max_limit' => 50],
    ]);

    $team = GamificationTeam::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'أسرة أحد',
        'coins' => 0,
    ]);

    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 100,
        'xp_amount' => 100,
        'description' => 'رصيد سابق',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    GamificationService::recalculateStudentState($this->student->id, $this->leaderboard->id);

    $error = null;
    expect(GamificationService::donateCoinsToTeam($this->student->id, $team->id, 5, $error))->toBeFalse();
    expect($error)->toContain('غير مفعلة');
});

it('deducts negative extra points from both coins and XP', function () {
    // Seed the student with an initial balance: 50 coins / 50 XP
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 50,
        'xp_amount' => 50,
        'description' => 'رصيد ابتدائي',
    ]);
    GamificationService::recalculateStudentState($this->student->id, $this->leaderboard->id);

    // Teacher applies a deduction of 20 via extra points
    $extraId = DB::table('leaderboard_extra_points')->insertGetId([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'date' => now()->format('Y-m-d'),
        'points' => -20,
        'notes' => 'خصم سلوكي',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    GamificationService::syncStudentExtraPointsXP($extraId);

    // Both the standing (XP) and coins must reflect the deduction
    expect(GamificationService::getStudentXP($this->student->id, $this->leaderboard->id))->toBe(30); // 50 - 20

    $state = GamificationStudentState::where('student_id', $this->student->id)
        ->where('leaderboard_id', $this->leaderboard->id)->first();
    expect($state->coins)->toBe(30); // 50 - 20
});

it('manages store purchases and voting', function () {
    $team = GamificationTeam::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'أسرة مكة',
        'coins' => 100,
    ]);

    $team->students()->attach($this->student->id, ['role' => 'leader']);

    // Add another member for voting
    $student2 = Student::create([
        'name' => 'محمد سعد',
        'email' => 'mohamed@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);
    $team->students()->attach($student2->id, ['role' => 'member']);

    $item = GamificationStoreItem::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'درع الحماية',
        'description' => 'يحمي الفريق من الهجمات',
        'price' => 80,
        'item_type' => 'shield',
        'is_team_product' => true,
    ]);

    // Enable purchase voting in settings
    $this->leaderboard->update([
        'settings' => array_merge($this->leaderboard->settings, ['team_purchase_voting_enabled' => true]),
    ]);

    // Leader requests team purchase with tomorrow's target date
    $status = GamificationService::requestStorePurchase($this->student->id, $item->id, null, now()->addDay()->format('Y-m-d'));
    expect($status)->toBe('pending_voting');

    $purchase = GamificationStorePurchase::where('store_item_id', $item->id)->first();
    expect($purchase->status)->toBe('pending_approval');

    // Student 2 votes yes (absolute majority is 2 out of 2)
    GamificationService::voteForPurchase($student2->id, $purchase->id, true);
    $purchase->refresh();
    expect($purchase->status)->toBe('approved'); // Approved!

    $team->refresh();
    expect($team->coins)->toBe(20); // 100 - 80
});

it('calculates points dynamically using multiplier factor', function () {
    // 1. Create a team and associate it with student
    $team = GamificationTeam::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'كتيبة النصر',
        'coins' => 100,
    ]);
    $team->students()->attach($this->student->id, ['role' => 'leader']);

    // 2. Create a 2x multiplier store item
    $multiplierItem = GamificationStoreItem::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'مضاعف نقاط ثنائي',
        'price' => 50,
        'item_type' => 'multiplier',
        'value' => 2,
        'is_team_product' => true,
    ]);

    // 3. Purchase the multiplier for tomorrow
    $tomorrow = now()->addDay()->format('Y-m-d');
    $status = GamificationService::requestStorePurchase($this->student->id, $multiplierItem->id, null, $tomorrow);
    expect($status)->toBe('success');

    // 4. Create a student plan and day for tomorrow
    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'plan_type' => 'hifz_review',
        'start_date' => now()->subDays(5),
        'is_approved' => 1,
        'days_count' => 30,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $day = StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => $tomorrow,
        'day_name' => 'السبت',
        'hifz_achievement' => 3, // Excellent -> 10 points
        'review_achievement' => 2, // Good -> 3 points
        'hifz_graded_at' => now(),
        'review_graded_at' => now(),
    ]);

    // Since this is a team multiplier, the individual student points remain 13 (not doubled).
    // But the team score should be doubled to 26.
    GamificationService::syncStudentPlanDayXP($day);

    $txs = GamificationTransaction::where('student_id', $this->student->id)
        ->where('reference_type', StudentPlanDay::class)
        ->where('reference_id', $day->id)
        ->get();

    expect($txs->sum('amount'))->toBe(13);

    $teamScore = GamificationService::getTeamScore($team, $this->leaderboard);
    expect($teamScore)->toBe(26);
});

it('applies the team multiplier to ode and hadith memorization points', function () {
    // Enable ode & hadith automatic scoring on the competition
    $settings = $this->leaderboard->settings;
    $settings['ode_hifz_enabled'] = true;
    $settings['ode_hifz_excellent_xp'] = 10;
    $settings['ode_hifz_excellent_coins'] = 10;
    $settings['hadith_hifz_enabled'] = true;
    $settings['hadith_hifz_excellent_xp'] = 8;
    $settings['hadith_hifz_excellent_coins'] = 8;
    $this->leaderboard->update(['settings' => $settings]);

    // Team + student
    $team = GamificationTeam::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'كتيبة النصر',
        'coins' => 100,
    ]);
    $team->students()->attach($this->student->id, ['role' => 'leader']);

    // 2x team multiplier active tomorrow
    $multiplierItem = GamificationStoreItem::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'مضاعف ثنائي',
        'price' => 50,
        'item_type' => 'multiplier',
        'value' => 2,
        'is_team_product' => true,
    ]);
    $tomorrow = now()->addDay()->format('Y-m-d');
    GamificationService::requestStorePurchase($this->student->id, $multiplierItem->id, null, $tomorrow);

    // Ode achievement graded tomorrow (excellent = 10)
    $ode = Ode::create(['name' => 'تحفة الأطفال']);
    $odePath = OdePath::create(['ode_id' => $ode->id, 'name' => 'مسار', 'start_date' => now()->subDays(5)]);
    $odeDay = OdePathDay::create(['ode_path_id' => $odePath->id, 'day_number' => 1, 'date' => $tomorrow]);
    $odePlan = StudentOdePlan::create([
        'student_id' => $this->student->id,
        'ode_path_id' => $odePath->id,
        'start_date' => now()->subDays(5),
        'status' => 'active',
        'created_by_role' => 'teacher',
    ]);
    $odeAch = StudentOdeAchievement::create([
        'student_ode_plan_id' => $odePlan->id,
        'ode_path_day_id' => $odeDay->id,
        'hifz_achievement' => 3,
        'hifz_graded_at' => Carbon::parse($tomorrow.' 10:00:00'),
    ]);

    GamificationService::syncStudentOdeAchievementXP($odeAch->fresh(['plan.student', 'pathDay']));

    // Individual XP is NOT doubled (team multiplier only)
    expect(GamificationService::getStudentXP($this->student->id, $this->leaderboard->id))->toBe(10);

    // Team score IS doubled: 10 * 2 = 20
    expect(GamificationService::getTeamScore($team, $this->leaderboard))->toBe(20);
});

it('groups standings into tracks with independent ranking and a general bucket', function () {
    $s2 = Student::create(['name' => 'طالب ب', 'email' => 'trk-b@example.com', 'password' => bcrypt('x'), 'circle_id' => $this->circle->id, 'is_approved' => true, 'status' => 'active']);
    $s3 = Student::create(['name' => 'طالب ج', 'email' => 'trk-c@example.com', 'password' => bcrypt('x'), 'circle_id' => $this->circle->id, 'is_approved' => true, 'status' => 'active']);

    // Scores: student=10, s2=30, s3=20 (all claimed)
    foreach ([[$this->student->id, 10], [$s2->id, 30], [$s3->id, 20]] as [$sid, $xp]) {
        GamificationTransaction::create([
            'leaderboard_id' => $this->leaderboard->id,
            'student_id' => $sid,
            'type' => 'earn',
            'amount' => $xp,
            'xp_amount' => $xp,
            'description' => 'كسب',
        ]);
    }

    // Track holds student (10) and s2 (30); s3 (20) is unassigned → "عام"
    $track = GamificationTrack::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'المتقدمون',
        'description' => 'وصف',
        'sort_order' => 1,
    ]);
    $track->students()->sync([$this->student->id, $s2->id]);

    $groups = (new LeaderboardService)->getStandingsByTrack($this->leaderboard);

    expect($groups)->toHaveCount(2);

    $trackGroup = $groups->firstWhere('name', 'المتقدمون');
    expect($trackGroup['description'])->toBe('وصف');
    // Ranked within track by score: s2 (30) #1, student (10) #2
    expect($trackGroup['standings'][0]['student']->id)->toBe($s2->id);
    expect($trackGroup['standings'][0]['track_rank'])->toBe(1);
    expect($trackGroup['standings'][1]['student']->id)->toBe($this->student->id);
    expect($trackGroup['standings'][1]['track_rank'])->toBe(2);

    // General bucket has only s3, ranked #1 within it
    $general = $groups->firstWhere('id', null);
    expect($general['name'])->toBe('عام');
    expect($general['standings'])->toHaveCount(1);
    expect($general['standings'][0]['student']->id)->toBe($s3->id);
    expect($general['standings'][0]['track_rank'])->toBe(1);
});

it('returns an empty collection from getStandingsByTrack when no tracks exist', function () {
    expect((new LeaderboardService)->getStandingsByTrack($this->leaderboard))->toHaveCount(0);
});

it('allows only the team leader to buy team products', function () {
    $assistant = Student::create(['name' => 'مساعد', 'email' => 'assist@example.com', 'password' => bcrypt('x'), 'circle_id' => $this->circle->id, 'is_approved' => true, 'status' => 'active']);

    $team = GamificationTeam::create(['leaderboard_id' => $this->leaderboard->id, 'name' => 'فريق', 'coins' => 100]);
    $team->students()->attach($this->student->id, ['role' => 'leader']);
    $team->students()->attach($assistant->id, ['role' => 'assistant']);

    $item = GamificationStoreItem::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'دعم الفريق',
        'price' => 20,
        'item_type' => 'team_points',
        'value' => 10,
        'is_team_product' => true,
        'is_active' => true,
    ]);

    // Assistant (and any non-leader) is blocked
    expect(GamificationService::requestStorePurchase($assistant->id, $item->id))->toBe('only_leader');

    // Leader is allowed
    expect(GamificationService::requestStorePurchase($this->student->id, $item->id))->not->toBe('only_leader');
});

it('records a level-up news item only when a student crosses to a higher level', function () {
    GamificationLevel::create(['leaderboard_id' => $this->leaderboard->id, 'level_number' => 1, 'name' => 'مبتدئ', 'xp_required' => 0, 'icon' => 'star']);
    GamificationLevel::create(['leaderboard_id' => $this->leaderboard->id, 'level_number' => 2, 'name' => 'متقدم', 'xp_required' => 100, 'icon' => 'star']);

    // Baseline at level 1 → no news
    GamificationService::recalculateStudentState($this->student->id, $this->leaderboard->id);
    expect(GamificationNews::where('type', 'level_up')->count())->toBe(0);

    // Earn 100 XP → reaches level 2
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 100,
        'xp_amount' => 100,
        'description' => 'كسب',
    ]);
    GamificationService::recalculateStudentState($this->student->id, $this->leaderboard->id);

    $news = GamificationNews::where('type', 'level_up')->get();
    expect($news)->toHaveCount(1);
    expect($news->first()->data['level'])->toBe(2);
    expect($news->first()->data['student_name'])->toBe($this->student->name);

    // Re-running does not duplicate
    GamificationService::recalculateStudentState($this->student->id, $this->leaderboard->id);
    expect(GamificationNews::where('type', 'level_up')->count())->toBe(1);
});

it('groups the daily digest by type and lists available dates', function () {
    GamificationNewsService::record($this->leaderboard->id, 'badge', ['student_name' => 'أ', 'badge_name' => 'وسام']);
    GamificationNewsService::record($this->leaderboard->id, 'badge', ['student_name' => 'ب', 'badge_name' => 'وسام']);
    GamificationNewsService::record($this->leaderboard->id, 'team_task', ['team_name' => 'فريق', 'task_name' => 'مهمة', 'grade' => 90]);
    GamificationNewsService::record($this->leaderboard->id, 'badge', ['student_name' => 'ج', 'badge_name' => 'وسام'], now()->subDay()->toDateString());

    $today = now()->toDateString();
    $digest = GamificationNewsService::getDailyDigest($this->leaderboard->id, $today);
    expect($digest['badge'])->toHaveCount(2);
    expect($digest['team_task'])->toHaveCount(1);

    $dates = GamificationNewsService::getAvailableDates($this->leaderboard->id);
    expect($dates)->toHaveCount(2);
    expect($dates[0])->toBe($today); // newest first
});

it('records team attacks anonymously and notes shield blocks', function () {
    $attacker = GamificationTeam::create(['leaderboard_id' => $this->leaderboard->id, 'name' => 'فريق المهاجم', 'coins' => 200]);
    $attacker->students()->attach($this->student->id, ['role' => 'leader']);
    $target = GamificationTeam::create(['leaderboard_id' => $this->leaderboard->id, 'name' => 'فريق الهدف', 'coins' => 100]);

    $item = GamificationStoreItem::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'خصم نقاط',
        'price' => 30,
        'item_type' => 'team_attack',
        'value' => 20,
        'is_team_product' => true,
        'is_active' => true,
    ]);

    // Successful attack
    GamificationService::requestStorePurchase($this->student->id, $item->id, $target->id);

    $attackNews = GamificationNews::where('type', 'team_attack')->first();
    expect($attackNews)->not->toBeNull();
    expect($attackNews->data['target_team_name'])->toBe('فريق الهدف');
    expect($attackNews->data['amount'])->toBe(20);
    // The attacker is NOT named anywhere in the payload
    expect(json_encode($attackNews->data))->not->toContain('المهاجم');

    // Now the target raises a shield → next attack is blocked
    $target->update(['shield_active_until' => now()->addDays(2)]);
    GamificationService::requestStorePurchase($this->student->id, $item->id, $target->id);

    $blocked = GamificationNews::where('type', 'team_attack_blocked')->first();
    expect($blocked)->not->toBeNull();
    expect($blocked->data['target_team_name'])->toBe('فريق الهدف');
    expect(json_encode($blocked->data))->not->toContain('المهاجم');
});
