<?php

use App\Models\Circle;
use App\Models\GamificationTrack;
use App\Models\Leaderboard;
use App\Models\LeaderboardCriterion;
use App\Models\LeaderboardScore;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'المرحلة الأولى']);
    $this->circle = Circle::create(['name' => 'حلقة النور', 'stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $this->supervisor->stages()->attach($this->stage->id);

    $this->studentTop = Student::create([
        'name' => 'الطالب المتصدر',
        'email' => 'top@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    $this->studentSecond = Student::create([
        'name' => 'الطالب الثاني',
        'email' => 'second@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    $this->leaderboard = Leaderboard::create([
        'supervisor_id' => $this->supervisor->id,
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الأوائل',
        'competition_type' => 'normal',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
        'settings' => [],
    ]);
    $this->leaderboard->circles()->attach($this->circle->id);

    $criterion = LeaderboardCriterion::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'الحفظ',
        'points' => 10,
    ]);

    LeaderboardScore::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->studentTop->id,
        'leaderboard_criterion_id' => $criterion->id,
        'date' => now(),
    ]);

    $this->track = GamificationTrack::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'مسار الحفظ',
        'sort_order' => 1,
    ]);
    $this->track->students()->sync([$this->studentTop->id, $this->studentSecond->id]);
});

it('shows the top-ranked students per track to the owning supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.competitions.standings', $this->leaderboard->id));

    $response->assertOk();
    $response->assertSee('مسار الحفظ');
    $response->assertSee('الطالب المتصدر');
});

it('does not let another supervisor view a competition they do not own', function () {
    $otherSupervisor = Supervisor::factory()->create(['is_approved' => true]);

    $response = $this->actingAs($otherSupervisor, 'supervisor')
        ->get(route('supervisor.competitions.standings', $this->leaderboard->id));

    $response->assertNotFound();
});

it('links to the standings page from the competitions list', function () {
    $response = $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.competitions'));

    $response->assertOk();
    $response->assertSee(route('supervisor.competitions.standings', $this->leaderboard->id), false);
});
