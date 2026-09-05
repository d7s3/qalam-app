<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\ExamLevel;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Supervisor;
use App\Models\Teacher;

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-07-08 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    $this->actingAs($this->supervisor, 'supervisor');
});

it('renders the supervisor dashboard with real counts instead of the old placeholder', function () {
    $response = $this->get(route('supervisor.dashboard'));

    $response->assertSuccessful();
    $response->assertDontSee('Placeholders for supervisor stats', false);
    $response->assertSee($this->circle->name);
    $response->assertSee('البرامج');
    $response->assertSee('الحلقات');
});

it('computes real weekly attendance percentage scoped to the supervisor stages', function () {
    Attendance::create(['student_id' => $this->student->id, 'teacher_id' => $this->teacher->id, 'circle_id' => $this->circle->id, 'date' => '2026-07-08', 'status' => 'present']);

    // Attendance from an unrelated circle/stage must not affect this supervisor's numbers.
    $otherStage = Stage::factory()->create();
    $otherCircle = Circle::factory()->create(['stage_id' => $otherStage->id]);
    $otherStudent = Student::factory()->create(['circle_id' => $otherCircle->id]);
    Attendance::create(['student_id' => $otherStudent->id, 'teacher_id' => $this->teacher->id, 'circle_id' => $otherCircle->id, 'date' => '2026-07-08', 'status' => 'absent']);

    $response = $this->get(route('supervisor.dashboard'));

    $response->assertSuccessful();
    $response->assertSee('100%');
});

it('counts upcoming exams and active competitions scoped to the supervisor', function () {
    $level = ExamLevel::create(['name' => 'مستوى تجريبي', 'direction' => 'nas_to_baqarah']);
    StudentExam::create(['student_id' => $this->student->id, 'exam_level_id' => $level->id, 'status' => 'pending', 'date_time' => now()->addDays(2)]);

    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id, 'title' => 'مسابقة', 'competition_type' => 'points',
        'start_date' => now()->subDay(), 'end_date' => now()->addDay(), 'is_active' => true, 'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $response = $this->get(route('supervisor.dashboard'));

    $response->assertSuccessful();
    $response->assertSee('اختبارات قادمة');
    $response->assertSee('مسابقات نشطة');
});
