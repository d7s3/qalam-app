<?php

use App\Livewire\Teacher\LeaderboardGrade;
use App\Livewire\Teacher\LeaderboardReport;
use App\Livewire\Teacher\Leaderboards;
use App\Models\Circle;
use App\Models\Leaderboard;
use App\Models\LeaderboardCriterion;
use App\Models\LeaderboardScore;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A teacher runs the competitions of his own cohorts and grades the ones his
 * cohorts take part in — nobody else's, whatever id the browser sends.
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-09-10 10:00:00');

    $stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $stage->id]);
    $this->otherCircle = Circle::factory()->create(['stage_id' => $stage->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['circle_id' => $this->circle->id, 'status' => 'active', 'is_approved' => true]);
    $this->stranger = Student::factory()->create(['circle_id' => $this->otherCircle->id, 'status' => 'active', 'is_approved' => true]);

    $this->mine = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقتي',
        'competition_type' => 'leaderboard',
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(10),
        'is_active' => true,
        'settings' => [],
    ]);

    $this->supervisors = Leaderboard::create([
        'supervisor_id' => Supervisor::factory()->create()->id,
        'circle_id' => $this->otherCircle->id,
        'title' => 'تلعيب المشرف',
        'competition_type' => 'gamification',
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(10),
        'is_active' => true,
        'settings' => [],
    ]);
    $this->supervisors->circles()->attach($this->otherCircle->id);

    $this->actingAs($this->teacher, 'teacher');
});

it('does not touch a competition that is not his own', function (string $action) {
    expect(fn () => Livewire::test(Leaderboards::class)->call($action, $this->supervisors->id))
        ->toThrow(ModelNotFoundException::class);

    expect($this->supervisors->fresh())->not->toBeNull()
        ->and($this->supervisors->fresh()->is_active)->toBeTrue();
})->with(['deleteLeaderboard', 'toggleActive', 'toggleActiveForGrading', 'edit']);

it('still deletes his own', function () {
    Livewire::test(Leaderboards::class)->call('deleteLeaderboard', $this->mine->id);

    expect($this->mine->fresh())->toBeNull();
});

it('cannot be pointed at another cohort from the browser', function () {
    Livewire::test(Leaderboards::class)->set('circleId', $this->otherCircle->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('does not open the grading or the report of a competition his cohorts are not in', function (string $component) {
    expect(fn () => Livewire::test($component, ['leaderboardId' => $this->supervisors->id]))
        ->toThrow(ModelNotFoundException::class);
})->with(['grading' => [LeaderboardGrade::class], 'report' => [LeaderboardReport::class]]);

it('scores only his own students, on the competition\'s own items', function () {
    $criterion = LeaderboardCriterion::create(['leaderboard_id' => $this->mine->id, 'name' => 'حضور مبكر', 'points' => 5]);
    $foreignCriterion = LeaderboardCriterion::create(['leaderboard_id' => $this->supervisors->id, 'name' => 'بند غيره', 'points' => 5]);

    Livewire::test(LeaderboardGrade::class, ['leaderboardId' => $this->mine->id])
        ->call('toggleScore', $this->stranger->id, $criterion->id, 5)
        ->assertForbidden();

    Livewire::test(LeaderboardGrade::class, ['leaderboardId' => $this->mine->id])
        ->call('toggleScore', $this->student->id, $foreignCriterion->id, 5)
        ->assertForbidden();

    Livewire::test(LeaderboardGrade::class, ['leaderboardId' => $this->mine->id])
        ->call('toggleScore', $this->student->id, $criterion->id, 5);

    expect(LeaderboardScore::pluck('student_id')->all())->toBe([$this->student->id]);
});

it('gives and takes extra points only within his own competition', function () {
    Livewire::test(LeaderboardGrade::class, ['leaderboardId' => $this->mine->id])
        ->call('saveExtraPoints', $this->stranger->id, 5, 'دخيل')
        ->assertForbidden();

    $foreignExtra = DB::table('leaderboard_extra_points')->insertGetId([
        'leaderboard_id' => $this->supervisors->id,
        'student_id' => $this->stranger->id,
        'date' => now()->toDateString(),
        'points' => 3,
        'notes' => 'نقاط غيره',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(LeaderboardGrade::class, ['leaderboardId' => $this->mine->id])
        ->call('deleteExtraPoints', $foreignExtra);

    expect(DB::table('leaderboard_extra_points')->where('id', $foreignExtra)->exists())->toBeTrue()
        ->and(DB::table('leaderboard_extra_points')->where('student_id', $this->stranger->id)->count())->toBe(1);
});
