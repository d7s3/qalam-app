<?php

use App\Models\Circle;
use App\Models\GamificationLevel;
use App\Models\GamificationStudentState;
use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);
});

/**
 * Build a gamification leaderboard with levels.
 *
 * @param  array<int, array{n: int, xp: int, reward?: int}>  $levels
 */
function levelRewardLeaderboard(int $circleId, bool $manualClaim, array $levels): Leaderboard
{
    $leaderboard = Leaderboard::create([
        'circle_id' => $circleId,
        'title' => 'مسابقة المستويات',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(10),
        'end_date' => now()->addDays(10),
        'is_active' => true,
        'settings' => ['manual_claim_enabled' => $manualClaim],
    ]);

    foreach ($levels as $level) {
        GamificationLevel::create([
            'leaderboard_id' => $leaderboard->id,
            'level_number' => $level['n'],
            'name' => 'المستوى '.$level['n'],
            'xp_required' => $level['xp'],
            'icon' => 'star',
            'settings' => ['reward_coins' => $level['reward'] ?? 0],
        ]);
    }

    return $leaderboard;
}

/** Grant claimed XP (no coins) so the student crosses levels. */
function grantXp(int $leaderboardId, int $studentId, int $xp): void
{
    GamificationTransaction::create([
        'leaderboard_id' => $leaderboardId,
        'student_id' => $studentId,
        'type' => 'earn',
        'amount' => 0,
        'xp_amount' => $xp,
        'description' => 'خبرة اختبارية',
    ]);
}

function levelRewards(int $leaderboardId, int $studentId)
{
    return GamificationTransaction::where('leaderboard_id', $leaderboardId)
        ->where('student_id', $studentId)
        ->where('reference_type', GamificationLevel::class)
        ->orderBy('reference_id')
        ->get();
}

it('grants a pending coin reward for every reached level (retroactively)', function () {
    $lb = levelRewardLeaderboard($this->circle->id, manualClaim: true, levels: [
        ['n' => 1, 'xp' => 0, 'reward' => 0],
        ['n' => 2, 'xp' => 100, 'reward' => 50],
        ['n' => 3, 'xp' => 200, 'reward' => 75],
    ]);

    grantXp($lb->id, $this->student->id, 250); // reaches level 3
    GamificationService::recalculateStudentState($this->student->id, $lb->id);

    $rewards = levelRewards($lb->id, $this->student->id);

    expect($rewards)->toHaveCount(2);
    expect($rewards->sum('amount'))->toBe(125);
    expect($rewards->pluck('xp_amount')->unique()->all())->toBe([0]); // coins only
    expect($rewards->whereNull('claimed_at'))->toHaveCount(2); // pending a manual claim

    // It shows up in the same pending-rewards list as everything else.
    $pending = GamificationService::getPendingRewards($this->student->id, $lb->id);
    expect($pending->where('reference_type', GamificationLevel::class))->toHaveCount(2);
});

it('does not reward levels the student has not reached yet', function () {
    $lb = levelRewardLeaderboard($this->circle->id, manualClaim: true, levels: [
        ['n' => 1, 'xp' => 0, 'reward' => 0],
        ['n' => 2, 'xp' => 100, 'reward' => 50],
        ['n' => 3, 'xp' => 200, 'reward' => 75],
    ]);

    grantXp($lb->id, $this->student->id, 150); // only level 2
    GamificationService::recalculateStudentState($this->student->id, $lb->id);

    $rewards = levelRewards($lb->id, $this->student->id);
    expect($rewards)->toHaveCount(1);
    expect($rewards->first()->amount)->toBe(50);
});

it('never duplicates a level reward across repeated recalculations', function () {
    $lb = levelRewardLeaderboard($this->circle->id, manualClaim: true, levels: [
        ['n' => 1, 'xp' => 0, 'reward' => 0],
        ['n' => 2, 'xp' => 100, 'reward' => 50],
    ]);

    grantXp($lb->id, $this->student->id, 120);

    GamificationService::recalculateStudentState($this->student->id, $lb->id);
    GamificationService::recalculateStudentState($this->student->id, $lb->id);
    GamificationService::recalculateStudentState($this->student->id, $lb->id);

    expect(levelRewards($lb->id, $this->student->id))->toHaveCount(1);
});

it('applies the level reward immediately when manual claim is disabled', function () {
    $lb = levelRewardLeaderboard($this->circle->id, manualClaim: false, levels: [
        ['n' => 1, 'xp' => 0, 'reward' => 0],
        ['n' => 2, 'xp' => 100, 'reward' => 50],
    ]);

    grantXp($lb->id, $this->student->id, 120);
    GamificationService::recalculateStudentState($this->student->id, $lb->id);

    $reward = levelRewards($lb->id, $this->student->id)->first();
    expect($reward->claimed_at)->not->toBeNull();

    // Immediately reflected in the coin balance.
    $state = GamificationStudentState::where('leaderboard_id', $lb->id)
        ->where('student_id', $this->student->id)->first();
    expect($state->coins)->toBe(50);
});

it('lets the student claim a pending level reward into their balance', function () {
    $lb = levelRewardLeaderboard($this->circle->id, manualClaim: true, levels: [
        ['n' => 1, 'xp' => 0, 'reward' => 0],
        ['n' => 2, 'xp' => 100, 'reward' => 50],
    ]);

    grantXp($lb->id, $this->student->id, 120);
    GamificationService::recalculateStudentState($this->student->id, $lb->id);

    $reward = levelRewards($lb->id, $this->student->id)->first();

    // Not counted while pending.
    $state = GamificationStudentState::where('leaderboard_id', $lb->id)
        ->where('student_id', $this->student->id)->first();
    expect($state->coins)->toBe(0);

    expect(GamificationService::claimReward($reward->id, $this->student->id))->toBeTrue();

    expect($reward->fresh()->claimed_at)->not->toBeNull();
    $state->refresh();
    expect($state->coins)->toBe(50);
});
