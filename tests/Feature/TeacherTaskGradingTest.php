<?php

use App\Models\Circle;
use App\Models\GamificationTeam;
use App\Models\GamificationTeamTask;
use App\Models\GamificationTeamTaskAssignment;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A team task is graded by the teacher it was handed to, and by no one else
 * from the dashboard — the grade pays the team its coins.
 */
beforeEach(function () {
    $circle = Circle::factory()->create(['stage_id' => Stage::factory()->create()->id]);
    $competition = Leaderboard::create([
        'circle_id' => $circle->id,
        'title' => 'تلعيب',
        'competition_type' => 'gamification',
        'start_date' => now()->subDay(),
        'is_active' => true,
        'settings' => [],
    ]);

    $task = GamificationTeamTask::create(['leaderboard_id' => $competition->id, 'name' => 'مهمة', 'xp_reward' => 10, 'coins_reward' => 10]);
    $team = GamificationTeam::create(['leaderboard_id' => $competition->id, 'name' => 'أسرة', 'coins' => 0]);

    $this->teacher = Teacher::factory()->create();
    $this->otherTeacher = Teacher::factory()->create();

    $this->his = GamificationTeamTaskAssignment::create(['team_task_id' => $task->id, 'team_id' => $team->id, 'teacher_id' => $this->teacher->id, 'start_date' => now(), 'end_date' => now()->addWeek()]);
    $this->theirs = GamificationTeamTaskAssignment::create(['team_task_id' => $task->id, 'team_id' => $team->id, 'teacher_id' => $this->otherTeacher->id, 'start_date' => now(), 'end_date' => now()->addWeek()]);

    $this->actingAs($this->teacher, 'teacher');
});

it('opens for grading only a task handed to him', function () {
    Livewire::test('teacher.dashboard')->call('editGrading', $this->his->id)->assertSet('showGradingModal', true);

    expect(fn () => Livewire::test('teacher.dashboard')->call('editGrading', $this->theirs->id))
        ->toThrow(ModelNotFoundException::class);
});

it('cannot be switched to another teacher\'s task before saving', function () {
    Livewire::test('teacher.dashboard')
        ->call('editGrading', $this->his->id)
        ->set('selectedAssignmentId', $this->theirs->id);
})->throws(CannotUpdateLockedPropertyException::class);
