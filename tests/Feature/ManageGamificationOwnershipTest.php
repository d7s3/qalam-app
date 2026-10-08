<?php

use App\Livewire\Supervisor\ManageGamification;
use App\Models\Circle;
use App\Models\GamificationActivity;
use App\Models\GamificationActivityRound;
use App\Models\GamificationBadge;
use App\Models\GamificationStoreItem;
use App\Models\GamificationTeam;
use App\Models\GamificationTeamTask;
use App\Models\GamificationTeamTaskAssignment;
use App\Models\GamificationTrack;
use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Supervisor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A supervisor's gamification screen reaches only the competition it was
 * opened for. Every badge, team, store item, task and adjustment belongs to
 * one competition, and an id from someone else's must be refused rather than
 * edited or deleted.
 */
function gamificationCompetitionFor(Supervisor $supervisor, string $title): Leaderboard
{
    $stage = Stage::create(['name' => $title.' مرحلة']);
    $circle = Circle::create(['name' => $title.' حلقة', 'stage_id' => $stage->id]);
    $supervisor->stages()->attach($stage->id);

    $competition = Leaderboard::create([
        'supervisor_id' => $supervisor->id,
        'circle_id' => $circle->id,
        'title' => $title,
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
    ]);
    $competition->circles()->attach($circle->id);

    return $competition;
}

beforeEach(function () {
    $this->supervisor = Supervisor::factory()->create();
    $this->competition = gamificationCompetitionFor($this->supervisor, 'مسابقتي');

    $this->otherSupervisor = Supervisor::factory()->create();
    $this->otherCompetition = gamificationCompetitionFor($this->otherSupervisor, 'مسابقة غيري');

    $this->actingAs($this->supervisor, 'supervisor');
});

it('refuses to switch the screen to another supervisor\'s competition', function () {
    Livewire::test(ManageGamification::class, ['competitionId' => $this->competition->id])
        ->set('competitionId', $this->otherCompetition->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('refuses to touch a record of another competition', function (Closure $makeRecord, string $action) {
    [$model, $id] = $makeRecord($this->otherCompetition);

    expect(fn () => Livewire::test(ManageGamification::class, ['competitionId' => $this->competition->id])
        ->call($action, $id))
        ->toThrow(ModelNotFoundException::class);

    expect($model::whereKey($id)->exists())->toBeTrue();
})->with([
    'delete badge' => [fn (Leaderboard $c) => [GamificationBadge::class, GamificationBadge::create(['leaderboard_id' => $c->id, 'name' => 'وسام', 'icon' => 'star', 'badge_type' => 'manual', 'requirement_value' => 0])->id], 'deleteBadge'],
    'edit badge' => [fn (Leaderboard $c) => [GamificationBadge::class, GamificationBadge::create(['leaderboard_id' => $c->id, 'name' => 'وسام', 'icon' => 'star', 'badge_type' => 'manual', 'requirement_value' => 0])->id], 'editBadge'],
    'grant badge' => [fn (Leaderboard $c) => [GamificationBadge::class, GamificationBadge::create(['leaderboard_id' => $c->id, 'name' => 'وسام', 'icon' => 'star', 'badge_type' => 'manual', 'requirement_value' => 0])->id], 'openGrantBadge'],
    'delete team' => [fn (Leaderboard $c) => [GamificationTeam::class, GamificationTeam::create(['leaderboard_id' => $c->id, 'name' => 'أسرة'])->id], 'deleteTeam'],
    'edit team' => [fn (Leaderboard $c) => [GamificationTeam::class, GamificationTeam::create(['leaderboard_id' => $c->id, 'name' => 'أسرة'])->id], 'editTeam'],
    'edit track' => [fn (Leaderboard $c) => [GamificationTrack::class, GamificationTrack::create(['leaderboard_id' => $c->id, 'name' => 'مسار'])->id], 'editTrack'],
    'delete item' => [fn (Leaderboard $c) => [GamificationStoreItem::class, GamificationStoreItem::create(['leaderboard_id' => $c->id, 'name' => 'منتج', 'price' => 10, 'item_type' => 'custom', 'is_team_product' => true])->id], 'deleteItem'],
    'toggle item' => [fn (Leaderboard $c) => [GamificationStoreItem::class, GamificationStoreItem::create(['leaderboard_id' => $c->id, 'name' => 'منتج', 'price' => 10, 'item_type' => 'custom', 'is_team_product' => true])->id], 'toggleProductStatus'],
    'edit item' => [fn (Leaderboard $c) => [GamificationStoreItem::class, GamificationStoreItem::create(['leaderboard_id' => $c->id, 'name' => 'منتج', 'price' => 10, 'item_type' => 'custom', 'is_team_product' => true])->id], 'editItem'],
    'delete team task' => [fn (Leaderboard $c) => [GamificationTeamTask::class, GamificationTeamTask::create(['leaderboard_id' => $c->id, 'name' => 'مهمة'])->id], 'deleteTeamTask'],
    'edit team task' => [fn (Leaderboard $c) => [GamificationTeamTask::class, GamificationTeamTask::create(['leaderboard_id' => $c->id, 'name' => 'مهمة'])->id], 'editTeamTask'],
    'delete assignment' => [function (Leaderboard $c) {
        $task = GamificationTeamTask::create(['leaderboard_id' => $c->id, 'name' => 'مهمة']);
        $team = GamificationTeam::create(['leaderboard_id' => $c->id, 'name' => 'أسرة']);

        return [GamificationTeamTaskAssignment::class, GamificationTeamTaskAssignment::create(['team_task_id' => $task->id, 'team_id' => $team->id, 'start_date' => now(), 'end_date' => now()])->id];
    }, 'deleteAssignment'],
    'delete activity' => [fn (Leaderboard $c) => [GamificationActivity::class, GamificationActivity::create(['leaderboard_id' => $c->id, 'name' => 'فعالية'])->id], 'deleteActivity'],
    'edit round winners' => [function (Leaderboard $c) {
        $activity = GamificationActivity::create(['leaderboard_id' => $c->id, 'name' => 'فعالية']);

        return [GamificationActivityRound::class, GamificationActivityRound::create(['activity_id' => $activity->id, 'name' => 'جولة', 'round_date' => now()])->id];
    }, 'editRoundWinners'],
    'delete adjustment' => [fn (Leaderboard $c) => [GamificationTransaction::class, GamificationTransaction::create(['leaderboard_id' => $c->id, 'team_id' => GamificationTeam::create(['leaderboard_id' => $c->id, 'name' => 'أسرة'])->id, 'type' => 'earn', 'amount' => 5, 'description' => 'تسوية'])->id], 'deleteAdjustment'],
]);

it('refuses to delete another competition\'s streak milestone', function () {
    $milestoneId = DB::table('gamification_streak_milestones')->insertGetId([
        'leaderboard_id' => $this->otherCompetition->id,
        'days_required' => 3,
        'reward_xp' => 1,
        'reward_coins' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => Livewire::test(ManageGamification::class, ['competitionId' => $this->competition->id])
        ->call('deleteMilestone', $milestoneId))
        ->toThrow(ModelNotFoundException::class);

    expect(DB::table('gamification_streak_milestones')->where('id', $milestoneId)->exists())->toBeTrue();
});

it('refuses to credit a team of another competition', function () {
    $foreignTeam = GamificationTeam::create(['leaderboard_id' => $this->otherCompetition->id, 'name' => 'أسرة غيري', 'coins' => 0]);

    Livewire::test(ManageGamification::class, ['competitionId' => $this->competition->id])
        ->set('adjTargetType', 'team')
        ->set('adjActionType', 'add')
        ->set('adjDescription', 'مكافأة')
        ->set('adjHasCoins', true)
        ->set('adjCoinsVal', 50)
        ->set('adjTeamId', $foreignTeam->id)
        ->call('applyAdjustment')
        ->assertHasErrors('adjTeamId');

    expect($foreignTeam->fresh()->coins)->toBe(0);
});

it('still deletes a badge of its own competition', function () {
    $badge = GamificationBadge::create(['leaderboard_id' => $this->competition->id, 'name' => 'وسامي', 'icon' => 'star', 'badge_type' => 'manual', 'requirement_value' => 0]);

    Livewire::test(ManageGamification::class, ['competitionId' => $this->competition->id])
        ->call('deleteBadge', $badge->id);

    expect(GamificationBadge::whereKey($badge->id)->exists())->toBeFalse();
});
