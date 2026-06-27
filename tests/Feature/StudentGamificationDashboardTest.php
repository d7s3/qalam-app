<?php

use App\Livewire\Teacher\LeaderboardGrade;
use App\Models\AcademicCalendarEvent;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\GamificationActivity;
use App\Models\GamificationActivityRound;
use App\Models\GamificationActivityWinner;
use App\Models\GamificationBadge;
use App\Models\GamificationLevel;
use App\Models\GamificationStoreItem;
use App\Models\GamificationStorePurchase;
use App\Models\GamificationStudentState;
use App\Models\GamificationTeam;
use App\Models\GamificationTeamTask;
use App\Models\GamificationTeamTaskAssignment;
use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Teacher;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-06-08 10:00:00');
    $this->stage = Stage::create(['name' => 'مرحلة اختبار لوحة الطالب']);
    $this->circle = Circle::create(['name' => 'حلقة اختبار لوحة الطالب', 'stage_id' => $this->stage->id]);

    $this->student = Student::create([
        'name' => 'طالب تجربة لوحة التحكم',
        'email' => 'student-dashboard@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    // Create an academic calendar event that defines all 7 days as working days
    // to ensure tests behave consistently regardless of the day of the week.
    AcademicCalendarEvent::create([
        'event_name' => 'دوام كامل التجريبي للتحقق',
        'start_date' => now()->subDays(30)->format('Y-m-d'),
        'end_date' => now()->addDays(30)->format('Y-m-d'),
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5, 6, 7], // All days are working days
        'is_visible' => true,
    ]);

    $this->actingAs($this->student, 'student');
});

it('renders the normal student dashboard when there is no active gamification leaderboard', function () {
    $response = $this->get(route('student.dashboard'));
    $response->assertSuccessful();
    $response->assertSee('محفوظي من القرآن الكريم');
    $response->assertDontSee('نظام التلعيب التفاعلي');
});

it('automatically overrides dashboard to themed gamification when active', function () {
    // 1. Create active gamification leaderboard
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الفضاء والمجرات للطلاب',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    // 2. Access dashboard
    $response = $this->get(route('student.dashboard'));
    $response->assertSuccessful();
    $response->assertSee('مسابقة الفضاء والمجرات للطلاب');
    $response->assertSee('منصة التاج الرقمية'); // Gamification header brand
    $response->assertDontSee('محفوظي من القرآن الكريم'); // Normal dashboard is overridden
});

it('renders student names in the leaderboard standings table', function () {
    // Regression guard: the standings table renders each row via the
    // `student.partials.leaderboard-row` Blade component. That file lives under
    // `resources/views/components/`, which Livewire's Blaze compiler treats as a
    // component path — compiling it into a callable function rather than plain
    // executable view code. Rendering it via `@include(...)` (a plain Blade
    // include) only *defines* that function and never calls it, so the row
    // silently renders as an empty string. It must be rendered as a real Blade
    // component (`<x-student.partials.leaderboard-row ... />`) instead.
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة لوحة المتصدرين',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $otherStudent = Student::create([
        'name' => 'الطالب المتصدر الأول',
        'email' => 'top-student@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    GamificationTransaction::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $otherStudent->id,
        'type' => 'earn',
        'amount' => 0,
        'xp_amount' => 500,
        'description' => 'كسب تجريبي',
        'claimed_at' => now(),
    ]);

    Livewire::test('student.gamification-dashboard')
        ->assertSee('لوحة المتصدرين')
        ->assertSee('الطالب المتصدر الأول')
        ->assertSee('طالب تجربة لوحة التحكم'); // The acting student also appears with a 0 score
});

it('supports student actions (buying, voting, donating) from the dashboard', function () {
    // 1. Create active gamification leaderboard
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الفضاء للطلاب',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [
            'team_purchase_voting_enabled' => true,
        ],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    // 2. Set student initial state and team
    $state = GamificationStudentState::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 150,
        'current_streak' => 3,
        'max_streak' => 3,
    ]);

    // Give them some earn transaction for initial XP.
    // Dated yesterday so the full 150 counts as the start-of-day donation base.
    GamificationTransaction::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 150,
        'xp_amount' => 300,
        'description' => 'كسب مبدئي',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    // Level that permits donating up to 100% of the start-of-day balance.
    GamificationLevel::create([
        'leaderboard_id' => $leaderboard->id,
        'level_number' => 1,
        'name' => 'مبتدئ',
        'xp_required' => 0,
        'icon' => 'sparkles',
        'settings' => ['has_donation' => true, 'donation_max_limit' => 100],
    ]);

    $anotherStudent = Student::create([
        'name' => 'طالب آخر',
        'email' => 'another-student@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    $team = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'سفينة النور الفضائية',
        'coins' => 50,
    ]);
    $team->students()->attach($this->student->id, ['role' => 'leader']);
    $team->students()->attach($anotherStudent->id, ['role' => 'member']);

    $storeItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'درع الحماية الفضي',
        'description' => 'حماية من الغيابات',
        'price' => 40,
        'item_type' => 'shield',
        'is_team_product' => true,
    ]);

    // Test Livewire component actions
    Livewire::test('student.gamification-dashboard')
        // Test Donating to Team
        ->call('donateToTeam', 30)
        ->assertHasNoErrors();

    $state->refresh();
    $team->refresh();
    expect($state->coins)->toBe(120); // 150 - 30
    expect($team->coins)->toBe(80); // 50 + 30

    // Test Buying Item (it is a team item 'shield', price is 40, team coins are 80 >= 40)
    Livewire::test('student.gamification-dashboard')
        ->set('targetDates.'.$storeItem->id, now()->addDay()->format('Y-m-d'))
        ->call('buyItem', $storeItem->id)
        ->assertHasNoErrors();

    // With voting enabled and >1 members, it goes to voting first
    $purchase = GamificationStorePurchase::where('store_item_id', $storeItem->id)->first();
    expect($purchase)->not->toBeNull();
    expect($purchase->status)->toBe('pending_approval');

    // Test Voting on Purchase: Log in as the other student to vote and meet threshold
    $this->actingAs($anotherStudent, 'student');
    Livewire::test('student.gamification-dashboard')
        ->call('votePurchase', $purchase->id, true)
        ->assertHasNoErrors();

    // 2 members in team, threshold is 2 => 2 yes votes executes purchase
    $purchase->refresh();
    expect($purchase->status)->toBe('approved');
    $team->refresh();
    expect($team->coins)->toBe(40); // 80 - 40
});

it('allows teachers to approve badges and students to claim them', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الفضاء للطلاب',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $state = GamificationStudentState::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 150,
        'current_streak' => 3,
        'max_streak' => 3,
    ]);

    GamificationTransaction::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 150,
        'description' => 'كسب مبدئي',
    ]);

    $badge = GamificationBadge::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'وسام التميز',
        'description' => 'يمنح للمتميزين',
        'icon' => 'star',
        'badge_type' => 'manual',
        'requirement_value' => 0,
        'reward_xp' => 50,
        'reward_coins' => 20,
    ]);

    // Grant badge to student initially (status defaults to pending_approval)
    DB::table('gamification_badge_student')->insert([
        'badge_id' => $badge->id,
        'student_id' => $this->student->id,
        'status' => 'pending_approval',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1. Teacher views grading page and approves the badge
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($this->circle->id);
    $this->actingAs($teacher, 'teacher');

    Livewire::test(LeaderboardGrade::class, ['leaderboardId' => $leaderboard->id])
        ->call('approveBadge', $badge->id, $this->student->id)
        ->assertHasNoErrors();

    // Verify status is now 'approved'
    $status = DB::table('gamification_badge_student')
        ->where('badge_id', $badge->id)
        ->where('student_id', $this->student->id)
        ->value('status');
    expect($status)->toBe('approved');

    // 2. Student views dashboard and claims the badge
    $this->actingAs($this->student, 'student');

    Livewire::test('student.gamification-dashboard')
        ->call('claimBadge', $badge->id)
        ->assertHasNoErrors();

    // Verify status is now 'claimed'
    $status = DB::table('gamification_badge_student')
        ->where('badge_id', $badge->id)
        ->where('student_id', $this->student->id)
        ->value('status');
    expect($status)->toBe('claimed');

    // Verify rewards are credited
    $coins = DB::table('gamification_student_states')
        ->where('leaderboard_id', $leaderboard->id)
        ->where('student_id', $this->student->id)
        ->value('coins');
    expect($coins)->toBe(170); // 150 (initial) + 20 (reward)
});

it('allows student to buy individual custom items and streak freeze cards', function () {
    // 1. Create active gamification leaderboard
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة القراءة والتفسير',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    // 2. Set student initial state and transactions
    $state = GamificationStudentState::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 200,
        'streak_freezes_count' => 0,
    ]);

    GamificationTransaction::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 200,
        'description' => 'كسب مبدئي للاختبار',
    ]);

    $freezeItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'بطاقة تجميد الحماسة',
        'price' => 120,
        'item_type' => 'custom',
        'is_team_product' => false,
        'is_streak_freeze' => true,
    ]);

    $customItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'هدية مخصصة',
        'price' => 50,
        'item_type' => 'custom',
        'is_team_product' => false,
        'is_streak_freeze' => false,
    ]);

    // Student buys the streak freeze card
    $this->actingAs($this->student, 'student');
    Livewire::test('student.gamification-dashboard')
        ->call('buyItem', $freezeItem->id)
        ->assertHasNoErrors();

    $state->refresh();
    expect($state->coins)->toBe(80); // 200 - 120
    expect($state->streak_freezes_count)->toBe(1); // Incremented!

    // Student buys the custom individual item
    Livewire::test('student.gamification-dashboard')
        ->call('buyItem', $customItem->id)
        ->assertHasNoErrors();

    $state->refresh();
    expect($state->coins)->toBe(30); // 80 - 50
    // streak freezes count stays 1
    expect($state->streak_freezes_count)->toBe(1);

    // Verify both purchases exist in the database with correct statuses
    $freezePurchase = GamificationStorePurchase::where('store_item_id', $freezeItem->id)->first();
    expect($freezePurchase)->not->toBeNull();
    expect($freezePurchase->status)->toBe('approved'); // Individual purchases execute immediately

    $customPurchase = GamificationStorePurchase::where('store_item_id', $customItem->id)->first();
    expect($customPurchase)->not->toBeNull();
    expect($customPurchase->status)->toBe('approved');
});

it('allows student to buy team attack targeting another team', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة هجوم المجموعات',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => ['team_purchase_voting_enabled' => false],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق المهاجمين',
        'coins' => 200,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    $targetTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق المدافعين',
        'coins' => 100,
    ]);

    $attackItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'صاروخ خصم نقاط',
        'price' => 80,
        'item_type' => 'team_attack',
        'value' => 30,
        'is_team_product' => true,
    ]);

    // Attack target team
    $this->actingAs($this->student, 'student');
    Livewire::test('student.gamification-dashboard')
        ->set('targetTeams.'.$attackItem->id, $targetTeam->id)
        ->call('buyItem', $attackItem->id)
        ->assertHasNoErrors();

    $myTeam->refresh();
    $targetTeam->refresh();

    expect($myTeam->coins)->toBe(120); // 200 - 80
    expect($targetTeam->coins)->toBe(70); // 100 - 30 (deducted!)

    // Check transaction created
    $tx = GamificationTransaction::where('team_id', $targetTeam->id)
        ->where('type', 'spend')
        ->where('amount', -30)
        ->first();
    expect($tx)->not->toBeNull();
});

it('allows team coins to become negative when targeted by a team attack and having insufficient coins', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة تحدي المجموعات',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => ['team_purchase_voting_enabled' => false],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق المهاجمين',
        'coins' => 200,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    $targetTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق الضحايا',
        'coins' => 10,
    ]);

    $attackItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'صاروخ خصم قوي',
        'price' => 80,
        'item_type' => 'team_attack',
        'value' => 50,
        'is_team_product' => true,
    ]);

    $this->actingAs($this->student, 'student');
    Livewire::test('student.gamification-dashboard')
        ->set('targetTeams.'.$attackItem->id, $targetTeam->id)
        ->call('buyItem', $attackItem->id)
        ->assertHasNoErrors();

    $myTeam->refresh();
    $targetTeam->refresh();

    expect($myTeam->coins)->toBe(120); // 200 - 80
    expect($targetTeam->coins)->toBe(-40); // 10 - 50
});

it('validates target date constraint on multiplier purchase', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة مضاعفة النقاط',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => ['team_purchase_voting_enabled' => false],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق المضاعفة',
        'coins' => 200,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    $multiplierItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'مضاعف النقاط',
        'price' => 50,
        'item_type' => 'multiplier',
        'value' => 2,
        'is_team_product' => true,
    ]);

    $this->actingAs($this->student, 'student');

    // 1. Try with invalid date (today or past)
    $today = now()->format('Y-m-d');
    Livewire::test('student.gamification-dashboard')
        ->set('targetDates.'.$multiplierItem->id, $today)
        ->call('buyItem', $multiplierItem->id)
        ->assertHasNoErrors();

    $myTeam->refresh();
    expect($myTeam->coins)->toBe(200); // No coins deducted!

    // 2. Try with valid date (tomorrow)
    $tomorrow = now()->addDay()->format('Y-m-d');
    Livewire::test('student.gamification-dashboard')
        ->set('targetDates.'.$multiplierItem->id, $tomorrow)
        ->call('buyItem', $multiplierItem->id)
        ->assertHasNoErrors();

    $myTeam->refresh();
    expect($myTeam->coins)->toBe(150); // 200 - 50

    $purchase = GamificationStorePurchase::where('store_item_id', $multiplierItem->id)->first();
    expect($purchase->target_date)->toBe($tomorrow);
});

it('deducts target team coins when a team attack with voting requirements is approved by team members', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة هجوم المجموعات مع تصويت',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => ['team_purchase_voting_enabled' => true],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق المهاجمين المصوتين',
        'coins' => 200,
    ]);

    // Attach leader and two members to myTeam (3 members total, threshold is 2)
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    $student2 = Student::create([
        'name' => 'طالب مصوت ثاني',
        'email' => 'student-voter2@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);
    $myTeam->students()->attach($student2->id, ['role' => 'member']);

    $student3 = Student::create([
        'name' => 'طالب مصوت ثالث',
        'email' => 'student-voter3@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);
    $myTeam->students()->attach($student3->id, ['role' => 'member']);

    $targetTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق مستهدف بالتصويت',
        'coins' => 100,
    ]);

    $attackItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'خصم جماعي معتمد',
        'price' => 80,
        'item_type' => 'team_attack',
        'value' => 40,
        'is_team_product' => true,
    ]);

    // 1. Leader purchases it. It goes to pending_approval.
    $this->actingAs($this->student, 'student');
    Livewire::test('student.gamification-dashboard')
        ->set('targetTeams.'.$attackItem->id, $targetTeam->id)
        ->call('buyItem', $attackItem->id)
        ->assertHasNoErrors();

    $myTeam->refresh();
    $targetTeam->refresh();

    // Check purchase created and is pending approval
    $purchase = GamificationStorePurchase::where('store_item_id', $attackItem->id)->first();
    expect($purchase)->not->toBeNull();
    expect($purchase->status)->toBe('pending_approval');
    expect($purchase->target_team_id)->toBe($targetTeam->id);

    // Coins shouldn't be deducted from either team yet
    expect($myTeam->coins)->toBe(200);
    expect($targetTeam->coins)->toBe(100);

    // 2. Member 2 votes YES. (Threshold is 2, since Leader voted yes, 1 more yes vote makes it 2 and approves it)
    $this->actingAs($student2, 'student');
    Livewire::test('student.gamification-dashboard')
        ->call('votePurchase', $purchase->id, true)
        ->assertHasNoErrors();

    $purchase->refresh();
    $myTeam->refresh();
    $targetTeam->refresh();

    // Now it should be approved, coins deducted from buyer team, and discount applied to target team
    expect($purchase->status)->toBe('approved');
    expect($myTeam->coins)->toBe(120); // 200 - 80
    expect($targetTeam->coins)->toBe(60); // 100 - 40
});

it('allows student to upload their custom profile avatar and compresses it to webp', function () {
    // Make sure storage disk public is clean
    Storage::fake('public');

    // Create active leaderboard
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الفضاء للطلاب',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $avatarFile = UploadedFile::fake()->image('avatar.jpg', 600, 600);

    $this->actingAs($this->student, 'student');

    Livewire::test('student.gamification-dashboard')
        ->set('profile_image_file', $avatarFile)
        ->assertHasNoErrors();

    $this->student->refresh();
    expect($this->student->avatar_path)->not->toBeNull();
    expect($this->student->avatar_path)->toStartWith('avatars/');
    expect($this->student->avatar_path)->toEndWith('.webp');

    Storage::disk('public')->assertExists($this->student->avatar_path);
});

it('calculates competition working days and their enthusiasm status correctly', function () {
    // 1. Create active leaderboard with settings
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الحماسة للطلاب',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'end_date' => now()->addDays(2)->format('Y-m-d'),
        'is_active' => true,
        'settings' => [
            'enthusiasm_enabled' => true,
            'enthusiasm_type' => 'attendance',
        ],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    // 2. Present attendance on 2 days ago (should be fiery)
    $teacher = Teacher::create([
        'name' => 'المعلم التجريبي',
        'email' => 'temp-teacher-enthusiasm@example.com',
        'password' => bcrypt('password'),
        'status' => 'active',
        'is_approved' => true,
    ]);

    Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $teacher->id,
        'circle_id' => $this->circle->id,
        'date' => now()->subDays(2)->format('Y-m-d'),
        'status' => 'present',
    ]);

    // Test dashboard view data
    Livewire::test('student.gamification-dashboard')
        ->assertViewHas('workingDays', function ($workingDays) {
            $twoDaysAgoStr = now()->subDays(2)->format('Y-m-d');
            $oneDayAgoStr = now()->subDays(1)->format('Y-m-d');
            $twoDaysHenceStr = now()->addDays(2)->format('Y-m-d');

            $day1 = collect($workingDays)->firstWhere('date', $twoDaysAgoStr);
            $day2 = collect($workingDays)->firstWhere('date', $oneDayAgoStr);
            $day3 = collect($workingDays)->firstWhere('date', $twoDaysHenceStr);

            if ($day1) {
                expect($day1['status'])->toBe('fiery');
            }
            if ($day2) {
                expect($day2['status'])->toBe('orange');
            }
            if ($day3) {
                expect($day3['status'])->toBe('gray');
            }

            return true;
        });
});

it('falls back to attendance only when no plan days are scheduled in both trigger mode', function () {
    // 1. Create active leaderboard with 'both' trigger mode
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الحماسة للطلاب - كلا الشرطين',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'end_date' => now()->addDays(2)->format('Y-m-d'),
        'is_active' => true,
        'settings' => [
            'enthusiasm_enabled' => true,
            'enthusiasm_type' => 'both',
        ],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    // Create a teacher
    $teacher = Teacher::create([
        'name' => 'المعلم التجريبي 2',
        'email' => 'temp-teacher-both@example.com',
        'password' => bcrypt('password'),
        'status' => 'active',
        'is_approved' => true,
    ]);

    // Present attendance on 2 days ago
    Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $teacher->id,
        'circle_id' => $this->circle->id,
        'date' => now()->subDays(2)->format('Y-m-d'),
        'status' => 'present',
    ]);

    Livewire::test('student.gamification-dashboard')
        ->assertViewHas('workingDays', function ($workingDays) {
            $twoDaysAgoStr = now()->subDays(2)->format('Y-m-d');
            $day1 = collect($workingDays)->firstWhere('date', $twoDaysAgoStr);

            expect($day1)->not->toBeNull();
            expect($day1['status'])->toBe('fiery');

            return true;
        });
});

it('requires students to manually claim milestone rewards from the dashboard', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الجوائز التفاعلية',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2)->format('Y-m-d'),
        'end_date' => now()->addDays(2)->format('Y-m-d'),
        'is_active' => true,
        'settings' => [
            'enthusiasm_enabled' => true,
            'enthusiasm_type' => 'attendance',
        ],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $milestone = DB::table('gamification_streak_milestones')->insertGetId([
        'leaderboard_id' => $leaderboard->id,
        'days_required' => 2,
        'reward_xp' => 50,
        'reward_coins' => 100,
        'description' => 'الوصول ليومين متتاليين',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $teacher = Teacher::create([
        'name' => 'المعلم التجريبي 3',
        'email' => 'temp-teacher-milestones@example.com',
        'password' => bcrypt('password'),
        'status' => 'active',
        'is_approved' => true,
    ]);

    // Present attendance on 2 days ago and 1 day ago (consecutive)
    Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $teacher->id,
        'circle_id' => $this->circle->id,
        'date' => now()->subDays(2)->format('Y-m-d'),
        'status' => 'present',
    ]);

    Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $teacher->id,
        'circle_id' => $this->circle->id,
        'date' => now()->subDay()->format('Y-m-d'),
        'status' => 'present',
    ]);

    // Triggers recalculation
    GamificationService::updateStudentStreak($this->student, now()->subDay()->format('Y-m-d'), $leaderboard);

    // Verify milestone record exists with status 'approved' and no transaction yet
    $claimedRecord = DB::table('gamification_claimed_milestones')
        ->where('student_id', $this->student->id)
        ->where('milestone_id', $milestone)
        ->first();

    expect($claimedRecord)->not->toBeNull();
    expect($claimedRecord->status)->toBe('approved');

    $txCount = GamificationTransaction::where('student_id', $this->student->id)
        ->where('description', 'like', 'مكافأة أيام الحماسة لـ%')
        ->count();
    expect($txCount)->toBe(0);

    // Now student opens dashboard and claims it
    $this->actingAs($this->student, 'student');

    $component = Livewire::test('student.gamification-dashboard');

    $freshRecord = DB::table('gamification_claimed_milestones')
        ->where('student_id', $this->student->id)
        ->where('milestone_id', $milestone)
        ->first();

    expect($freshRecord)->not->toBeNull();
    expect($freshRecord->status)->toBe('approved');

    $component->call('claimMilestone', $freshRecord->id)
        ->assertHasNoErrors();

    // Verify milestone record status is now 'claimed'
    $claimedRecord = DB::table('gamification_claimed_milestones')
        ->where('student_id', $this->student->id)
        ->where('milestone_id', $milestone)
        ->first();
    expect($claimedRecord)->not->toBeNull();
    expect($claimedRecord->status)->toBe('claimed');

    // Verify transaction exists and rewards credited
    $tx = GamificationTransaction::where('student_id', $this->student->id)
        ->where('description', 'like', 'مكافأة أيام الحماسة لـ%')
        ->first();
    expect($tx)->not->toBeNull();
    expect($tx->amount)->toBe(100);
    expect($tx->xp_amount)->toBe(50);

    $state = GamificationStudentState::where('student_id', $this->student->id)
        ->where('leaderboard_id', $leaderboard->id)
        ->first();
    expect($state->coins)->toBe(100); // 100 reward coins
});

it('allows student to open double points modal and complete purchase', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة مضاعفة النقاط',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => ['team_purchase_voting_enabled' => false],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق التميز',
        'coins' => 300,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    $this->actingAs($this->student, 'student');

    $tomorrow = now()->addDay()->format('Y-m-d');

    Livewire::test('student.gamification-dashboard')
        ->assertSet('showDoublePointsModal', false)
        ->call('openDoublePointsModal', $tomorrow)
        ->assertSet('showDoublePointsModal', true)
        ->assertSet('doublePointsDate', $tomorrow)
        ->call('purchaseDoublePoints')
        ->assertSet('showDoublePointsModal', false)
        ->assertHasNoErrors();

    // Verify team coins decreased by 150
    $myTeam->refresh();
    expect($myTeam->coins)->toBe(150); // 300 - 150

    // Verify store item was created
    $item = GamificationStoreItem::where('leaderboard_id', $leaderboard->id)
        ->where('item_type', 'multiplier')
        ->first();
    expect($item)->not->toBeNull();
    expect($item->price)->toBe(150);

    // Verify purchase record exists and is approved/success
    $purchase = GamificationStorePurchase::where('store_item_id', $item->id)->first();
    expect($purchase)->not->toBeNull();
    expect($purchase->target_date)->toBe($tomorrow);
    expect($purchase->status)->toBe('approved');
});

it('allows student to buy individual double points multiplier from coins', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة مضاعفة النقاط الفردية',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    // Initial student state with 200 coins
    $state = GamificationStudentState::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 200,
    ]);

    GamificationTransaction::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 200,
        'description' => 'كسب مبدئي للتحقق',
    ]);

    $this->actingAs($this->student, 'student');

    $tomorrow = now()->addDay()->format('Y-m-d');

    Livewire::test('student.gamification-dashboard')
        ->assertSet('showDoublePointsModal', false)
        ->call('openDoublePointsModal', $tomorrow, 'individual')
        ->assertSet('showDoublePointsModal', true)
        ->assertSet('doublePointsDate', $tomorrow)
        ->assertSet('doublePointsType', 'individual')
        ->call('purchaseDoublePoints')
        ->assertSet('showDoublePointsModal', false)
        ->assertHasNoErrors();

    // Verify student coins decreased by 150
    $state->refresh();
    expect($state->coins)->toBe(50); // 200 - 150

    // Verify store item was created as individual product
    $item = GamificationStoreItem::where('leaderboard_id', $leaderboard->id)
        ->where('item_type', 'multiplier')
        ->where('is_team_product', false)
        ->first();
    expect($item)->not->toBeNull();
    expect($item->price)->toBe(150);

    // Verify purchase record exists and is approved
    $purchase = GamificationStorePurchase::where('store_item_id', $item->id)->first();
    expect($purchase)->not->toBeNull();
    expect($purchase->target_date)->toBe($tomorrow);
    expect($purchase->student_id)->toBe($this->student->id);
    expect($purchase->team_id)->toBeNull();
    expect($purchase->status)->toBe('approved');
});

it('fails to purchase double points multiplier if target date is not a working day', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة فاشلة للتحقق',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    // Delete calendar event to fall back to default weekdays (Sun-Thu)
    AcademicCalendarEvent::truncate();

    // Let's find a weekend day (Friday/Saturday) tomorrow or the day after
    $targetDate = null;
    for ($i = 1; $i <= 7; $i++) {
        $checkDate = now()->addDays($i);
        if (in_array($checkDate->dayOfWeek + 1, [6, 7])) { // Friday (6) or Saturday (7)
            $targetDate = $checkDate->format('Y-m-d');
            break;
        }
    }

    $state = GamificationStudentState::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 200,
    ]);

    GamificationTransaction::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 200,
        'description' => 'كسب مبدئي للتحقق',
    ]);

    $this->actingAs($this->student, 'student');

    Livewire::test('student.gamification-dashboard')
        ->call('openDoublePointsModal', $targetDate, 'individual')
        ->assertSet('showDoublePointsModal', true)
        ->set('doublePointsDate', $targetDate)
        ->call('purchaseDoublePoints')
        ->assertSet('showDoublePointsModal', true); // Modal remains open because of failure

    // Verify student coins did NOT decrease
    $state->refresh();
    expect($state->coins)->toBe(200);
});

it('prevents purchasing duplicate multipliers on the same day and caps points multiplier at 2', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة مضاعفات النقاط',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => ['team_purchase_voting_enabled' => false],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق التميز',
        'coins' => 300,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    $state = GamificationStudentState::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 300,
    ]);

    $this->actingAs($this->student, 'student');

    $tomorrow = now()->addDay()->format('Y-m-d');

    // 1. Purchase first multiplier (individual)
    Livewire::test('student.gamification-dashboard')
        ->call('openDoublePointsModal', $tomorrow, 'individual')
        ->call('purchaseDoublePoints')
        ->assertHasNoErrors();

    // Verify first purchase exists
    $purchase1 = GamificationStorePurchase::where('student_id', $this->student->id)
        ->whereNull('team_id')
        ->first();
    expect($purchase1)->not->toBeNull();

    // 2. Purchase second multiplier (team) for the same day -> should succeed under new rules
    Livewire::test('student.gamification-dashboard')
        ->call('openDoublePointsModal', $tomorrow, 'team')
        ->call('purchaseDoublePoints')
        ->assertHasNoErrors();

    // Verify team purchase was created successfully
    $purchase2 = GamificationStorePurchase::where('team_id', $myTeam->id)->first();
    expect($purchase2)->not->toBeNull();

    // 3. Purchase a duplicate individual multiplier for the same day -> should fail (no duplicate individual)
    Livewire::test('student.gamification-dashboard')
        ->call('openDoublePointsModal', $tomorrow, 'individual')
        ->call('purchaseDoublePoints')
        ->assertHasNoErrors();

    // Check that we only have 1 individual purchase
    $individualPurchasesCount = GamificationStorePurchase::where('student_id', $this->student->id)
        ->whereNull('team_id')
        ->count();
    expect($individualPurchasesCount)->toBe(1);

    // 4. Purchase a duplicate team multiplier for the same day -> should fail (no duplicate team)
    Livewire::test('student.gamification-dashboard')
        ->call('openDoublePointsModal', $tomorrow, 'team')
        ->call('purchaseDoublePoints')
        ->assertHasNoErrors();

    // Check that we only have 1 team purchase
    $teamPurchasesCount = GamificationStorePurchase::where('team_id', $myTeam->id)->count();
    expect($teamPurchasesCount)->toBe(1);

    // Calculate final multiplier for that date -> must be 2, not 4
    $multiplier = 1;
    $multiplier = max($multiplier, GamificationService::getMultiplierForTeam($myTeam, $tomorrow));
    $multiplier = max($multiplier, GamificationService::getMultiplierForStudent($this->student, $leaderboard->id, $tomorrow));
    $multiplier = min(2, $multiplier);
    expect($multiplier)->toBe(2);
});

it('supports manual individual streak freezes with level based day range expansion', function () {
    // 1. Create active gamification leaderboard with levels
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة تجميد الحماسة',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(10)->format('Y-m-d'),
        'end_date' => now()->addDays(10)->format('Y-m-d'),
        'is_active' => true,
        'settings' => [
            'enthusiasm_enabled' => true,
            'enthusiasm_type' => 'attendance',
            'streak_freeze_levels' => [
                ['level' => 3, 'days' => 2], // Level 3 can freeze up to 2 previous days
                ['level' => 5, 'days' => 3], // Level 5 can freeze up to 3 previous days
            ],
        ],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    // Create permanent freeze store item
    $freezeItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'تجميد أيام الحماسة ❄️',
        'price' => 100,
        'item_type' => 'freeze',
        'is_team_product' => false,
        'is_streak_freeze' => true,
        'is_active' => true,
    ]);

    // Define some levels
    GamificationLevel::create([
        'leaderboard_id' => $leaderboard->id,
        'level_number' => 1,
        'name' => 'المستوى 1',
        'xp_required' => 0,
        'settings' => [
            'has_freeze' => true,
            'freeze_price' => 100,
            'freeze_max_days' => 1,
        ],
    ]);
    GamificationLevel::create([
        'leaderboard_id' => $leaderboard->id,
        'level_number' => 3,
        'name' => 'المستوى 3',
        'xp_required' => 500,
        'settings' => [
            'has_freeze' => true,
            'freeze_price' => 100,
            'freeze_max_days' => 2,
        ],
    ]);

    $state = GamificationStudentState::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 250,
    ]);

    GamificationTransaction::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 250,
        'description' => 'كسب مبدئي للاختبار',
    ]);

    // Student is currently Level 1 (0 XP)
    // 1. Verify default freezeable dates range is today and yesterday (1 day previous)
    $todayStr = now()->format('Y-m-d');
    $yesterdayStr = now()->subDay()->format('Y-m-d');
    $twoDaysAgoStr = now()->subDays(2)->format('Y-m-d');

    $freezeableDates = GamificationService::getFreezeableDates($this->student, $leaderboard);
    expect($freezeableDates)->toContain($todayStr);
    expect($freezeableDates)->toContain($yesterdayStr);
    expect($freezeableDates)->not->toContain($twoDaysAgoStr);

    // 2. Add XP to reach Level 3
    GamificationTransaction::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'type' => 'earn',
        'amount' => 0,
        'xp_amount' => 600, // Reaches level 3
        'description' => 'كسب خبرة للترقية',
    ]);

    // Clear static level caches if any
    $freezeableDatesLvl3 = GamificationService::getFreezeableDates($this->student, $leaderboard);
    expect($freezeableDatesLvl3)->toContain($todayStr);
    expect($freezeableDatesLvl3)->toContain($yesterdayStr);
    expect($freezeableDatesLvl3)->toContain($twoDaysAgoStr); // Now unlocked!

    // 3. Test purchase of freeze for yesterday (working day)
    $this->actingAs($this->student, 'student');

    Livewire::test('student.gamification-dashboard')
        ->call('openFreezeModal', $yesterdayStr, 'أمس', 'البلدي')
        ->call('purchaseFreeze')
        ->assertHasNoErrors();

    // Verify coin deduction
    $state->refresh();
    expect($state->coins)->toBe(150); // 250 - 100

    // Verify purchase exists and is approved
    $purchase = GamificationStorePurchase::where('student_id', $this->student->id)
        ->where('target_date', $yesterdayStr)
        ->whereHas('item', fn ($q) => $q->where('is_streak_freeze', true))
        ->first();
    expect($purchase)->not->toBeNull();
    expect($purchase->status)->toBe('approved');

    // 4. Test purchase fail due to insufficient coins
    GamificationTransaction::where('student_id', $this->student->id)
        ->where('description', 'كسب مبدئي للاختبار')
        ->update(['amount' => 150]);
    GamificationService::recalculateStudentState($this->student->id, $leaderboard->id);

    Livewire::test('student.gamification-dashboard')
        ->call('openFreezeModal', $twoDaysAgoStr, 'قبل أمس', 'البلدي')
        ->call('purchaseFreeze')
        ->assertHasNoErrors();

    // Verify second purchase was not created
    $secondPurchase = GamificationStorePurchase::where('student_id', $this->student->id)
        ->where('target_date', $twoDaysAgoStr)
        ->first();
    expect($secondPurchase)->toBeNull();
});

it('ensures team multiplier doubles team score but does not affect student individual score', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة مضاعفات الفريق',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [
            'hifz_enabled' => true,
        ],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق الأبطال',
        'coins' => 500,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    $state = GamificationStudentState::create([
        'leaderboard_id' => $leaderboard->id,
        'student_id' => $this->student->id,
        'coins' => 500,
    ]);

    $tomorrow = now()->addDay()->format('Y-m-d');

    // 1. Purchase team multiplier for tomorrow
    $teamItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'مضاعف جماعي',
        'price' => 100,
        'item_type' => 'multiplier',
        'is_team_product' => true,
        'value' => 2,
    ]);
    GamificationStorePurchase::create([
        'store_item_id' => $teamItem->id,
        'student_id' => $this->student->id,
        'team_id' => $myTeam->id,
        'price_paid' => 100,
        'target_date' => $tomorrow,
        'status' => 'approved',
    ]);

    // 2. Create student plan day for tomorrow
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
        'hifz_graded_at' => now(),
    ]);

    // Sync XP
    GamificationService::syncStudentPlanDayXP($day);

    // Assert student's individual XP is 10 (not doubled)
    $studentXP = GamificationService::getStudentXP($this->student->id, $leaderboard->id);
    expect($studentXP)->toBe(10);

    // Assert team score is 20 (doubled by team multiplier)
    $teamScore = GamificationService::getTeamScore($myTeam, $leaderboard);
    expect($teamScore)->toBe(20);

    // 3. Now add individual multiplier for tomorrow as well
    $individualItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'مضاعف فردي',
        'price' => 100,
        'item_type' => 'multiplier',
        'is_team_product' => false,
        'value' => 2,
    ]);
    GamificationStorePurchase::create([
        'store_item_id' => $individualItem->id,
        'student_id' => $this->student->id,
        'price_paid' => 100,
        'target_date' => $tomorrow,
        'status' => 'approved',
    ]);

    // Re-sync XP
    GamificationService::syncStudentPlanDayXP($day);

    // Assert student's individual XP is now 20 (doubled by individual multiplier)
    $studentXP2 = GamificationService::getStudentXP($this->student->id, $leaderboard->id);
    expect($studentXP2)->toBe(20);

    // Assert team score remains 20 (capped at 2x max, does not compound to 40)
    $teamScore2 = GamificationService::getTeamScore($myTeam, $leaderboard);
    expect($teamScore2)->toBe(20);
});

it('displays team tasks on the student dashboard team tab', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة المهام',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق الفرسان',
        'coins' => 100,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    // Create a team task
    $task = GamificationTeamTask::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'مهمة الضيافة الأسبوعية',
        'description' => 'ترتيب القهوة والشاي',
        'evaluation_criteria' => 'البشاشة والسرعة في الضيافة',
        'xp_reward' => 80,
        'coins_reward' => 120,
    ]);

    $assignment = GamificationTeamTaskAssignment::create([
        'team_task_id' => $task->id,
        'team_id' => $myTeam->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addDay()->format('Y-m-d'),
        'status' => 'assigned',
    ]);

    $this->actingAs($this->student, 'student');

    Livewire::test('student.gamification-dashboard')
        ->assertViewHas('teamTasks', function ($tasks) use ($assignment) {
            return $tasks->count() === 1 && $tasks->first()->id === $assignment->id;
        })
        ->assertSee('مهام ال')
        ->assertSee('مهمة الضيافة الأسبوعية')
        ->assertSee('ترتيب القهوة والشاي')
        ->assertSee('البشاشة والسرعة في الضيافة')
        ->assertSee('قيد التنفيذ');
});

it('lists the team purchased products with used / not-used classification on the team tab', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة مشتريات المجموعة',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق المؤن',
        'coins' => 500,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    // A multiplier scheduled for the future -> "not used yet".
    $futureMultiplier = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'مضاعف نقاط الغد',
        'price' => 100,
        'item_type' => 'multiplier',
        'is_team_product' => true,
        'value' => 2,
    ]);
    GamificationStorePurchase::create([
        'store_item_id' => $futureMultiplier->id,
        'student_id' => $this->student->id,
        'team_id' => $myTeam->id,
        'price_paid' => 100,
        'target_date' => now()->addDays(2)->format('Y-m-d'),
        'status' => 'approved',
    ]);

    // A shield whose protected day has passed -> "used".
    $pastShield = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'درع الأمس',
        'price' => 80,
        'item_type' => 'shield',
        'is_team_product' => true,
    ]);
    GamificationStorePurchase::create([
        'store_item_id' => $pastShield->id,
        'student_id' => $this->student->id,
        'team_id' => $myTeam->id,
        'price_paid' => 80,
        'target_date' => now()->subDays(2)->format('Y-m-d'),
        'status' => 'approved',
    ]);

    // An instant team-points purchase (no target date) -> "used".
    $teamPoints = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'دعم نقاط الفريق',
        'price' => 60,
        'item_type' => 'team_points',
        'is_team_product' => true,
        'value' => 50,
    ]);
    GamificationStorePurchase::create([
        'store_item_id' => $teamPoints->id,
        'student_id' => $this->student->id,
        'team_id' => $myTeam->id,
        'price_paid' => 60,
        'status' => 'approved',
    ]);

    // A pending (not yet approved) purchase must NOT appear in the inventory list.
    $pendingItem = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'منتج بانتظار التصويت',
        'price' => 40,
        'item_type' => 'shield',
        'is_team_product' => true,
    ]);
    GamificationStorePurchase::create([
        'store_item_id' => $pendingItem->id,
        'student_id' => $this->student->id,
        'team_id' => $myTeam->id,
        'price_paid' => 40,
        'status' => 'pending_approval',
    ]);

    $this->actingAs($this->student, 'student');

    Livewire::test('student.gamification-dashboard')
        ->assertViewHas('teamPurchases', function ($purchases) {
            return $purchases->count() === 3
                && $purchases->every(fn ($p) => $p->status === 'approved');
        })
        ->assertSee('مشتريات ومنتجات ال')
        ->assertSee('مضاعف نقاط الغد')
        ->assertSee('درع الأمس')
        ->assertSee('دعم نقاط الفريق')
        ->assertSee('لم يُستخدم بعد')
        ->assertSee('مُستخدَم');
});

it('classifies team purchase usage states by target date', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة تصنيف المشتريات',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $item = GamificationStoreItem::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'مضاعف',
        'price' => 100,
        'item_type' => 'multiplier',
        'is_team_product' => true,
    ]);

    $makePurchase = fn (?string $targetDate) => new GamificationStorePurchase([
        'store_item_id' => $item->id,
        'target_date' => $targetDate,
    ]);

    $this->actingAs($this->student, 'student');
    $component = Livewire::test('student.gamification-dashboard')->instance();

    expect($component->teamPurchaseUsage($makePurchase(now()->addDay()->format('Y-m-d'))))
        ->toMatchArray(['state' => 'scheduled', 'used' => false]);

    expect($component->teamPurchaseUsage($makePurchase(now()->format('Y-m-d'))))
        ->toMatchArray(['state' => 'active', 'used' => true]);

    expect($component->teamPurchaseUsage($makePurchase(now()->subDay()->format('Y-m-d'))))
        ->toMatchArray(['state' => 'used', 'used' => true]);

    expect($component->teamPurchaseUsage($makePurchase(null)))
        ->toMatchArray(['state' => 'used', 'used' => true]);
});

it('animates the team page with a sky-blue aura only when shield protection is active today', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة درع الحماية',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $team = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق الدرع',
        'coins' => 100,
        'shield_active_until' => now()->addDays(2),
    ]);
    $team->students()->attach($this->student->id, ['role' => 'leader']);

    $this->actingAs($this->student, 'student');

    Livewire::test('student.gamification-dashboard')
        ->assertViewHas('teamShieldActiveToday', true)
        ->assertSee('team-shield-aura');

    // Once the shield has expired, the aura must not be applied.
    $team->update(['shield_active_until' => now()->subDay()]);

    Livewire::test('student.gamification-dashboard')
        ->assertViewHas('teamShieldActiveToday', false)
        ->assertDontSee('team-shield-aura');
});

it('displays gamification activities and recorded winners on the student dashboard team tab', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة التحدي الكبرى',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $myTeam = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'أسرة الهمة العالية',
        'coins' => 100,
    ]);
    $myTeam->students()->attach($this->student->id, ['role' => 'leader']);

    // Create Activity, Rank, Round, and Winner
    $activity = GamificationActivity::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'دوري تنس الطاولة الأسبوعي',
        'description' => 'دوري تنس الطاولة الفردي والجماعي',
    ]);
    $rank = $activity->ranks()->create([
        'name' => 'المركز الأول',
        'team_xp' => 120,
        'team_coins' => 80,
        'member_xp' => 40,
        'member_coins' => 30,
    ]);
    $round = GamificationActivityRound::create([
        'activity_id' => $activity->id,
        'name' => 'الجولة الأولى',
        'round_date' => now()->format('Y-m-d'),
    ]);
    $winner = GamificationActivityWinner::create([
        'round_id' => $round->id,
        'rank_id' => $rank->id,
        'team_id' => $myTeam->id,
    ]);

    $this->actingAs($this->student, 'student');

    Livewire::test('student.gamification-dashboard')
        ->assertViewHas('teamActivities', function ($activities) use ($activity) {
            return $activities->count() === 1 && $activities->first()->id === $activity->id;
        })
        ->assertViewHas('allActivityRounds', function ($rounds) use ($round) {
            return $rounds->count() === 1 && $rounds->first()->id === $round->id;
        })
        ->assertSee('لوحة شرف الفعاليات والأنشطة المشتركة')
        ->assertSee('دوري تنس الطاولة الأسبوعي')
        ->assertSee('دوري تنس الطاولة الفردي والجماعي')
        ->assertSee('أسرة الهمة العالية')
        ->assertSee('الجولة الأولى');
});
