<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\GamificationBadge;
use App\Models\GamificationStoreItem;
use App\Models\GamificationStorePurchase;
use App\Models\GamificationStudentState;
use App\Models\GamificationTeam;
use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Teacher;
use App\Services\GamificationService;
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
        'theme_key' => 'space',
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
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 100,
        'xp_amount' => 400,
        'description' => 'رصيد ابتدائي للاختبار',
    ]);
    GamificationService::recalculateStudentState($this->student->id, $this->leaderboard->id);

    $team = GamificationTeam::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'أسرة مكة',
        'coins' => 50,
    ]);

    $result = GamificationService::donateCoinsToTeam($this->student->id, $team->id, 40);
    expect($result)->toBeTrue();

    $state = GamificationStudentState::where('student_id', $this->student->id)->where('leaderboard_id', $this->leaderboard->id)->first();
    $state->refresh();
    $team->refresh();

    expect($state->coins)->toBe(60); // 100 - 40
    expect($team->coins)->toBe(90); // 50 + 40
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
