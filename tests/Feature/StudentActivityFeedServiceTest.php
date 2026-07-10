<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\ExamLevel;
use App\Models\GamificationNews;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Teacher;
use App\Services\StudentActivityFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-06-10 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);
    $this->classmate = Student::factory()->create(['circle_id' => $this->circle->id]);
});

it('returns an empty feed for a student with no activity', function () {
    expect(StudentActivityFeedService::recentActivity($this->student))->toBe([]);
});

it('merges grading, attendance, and exam events sorted newest-first', function () {
    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'plan_type' => 'hifz',
        'start_date' => now()->subDays(5),
        'is_approved' => true,
        'days_count' => 30,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => '2026-06-08',
        'day_name' => 'يوم',
        'hifz_achievement' => 3,
        'hifz_graded_at' => '2026-06-08 12:00:00',
    ]);

    Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $this->circle->id,
        'date' => '2026-06-09',
        'status' => 'present',
    ]);

    $examLevel = ExamLevel::create(['name' => 'المستوى الأول']);
    StudentExam::create([
        'student_id' => $this->student->id,
        'exam_level_id' => $examLevel->id,
        'status' => 'completed',
        'date_time' => '2026-06-10 09:00:00',
    ]);

    $feed = StudentActivityFeedService::recentActivity($this->student);

    expect($feed)->toHaveCount(3);
    expect($feed[0]['type'])->toBe('exam');
    expect($feed[1]['type'])->toBe('attendance');
    expect($feed[2]['type'])->toBe('hifz');
});

it('includes the student own gamification news when a leaderboard is active', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة تجريبية',
        'competition_type' => 'points',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);

    GamificationNews::create([
        'leaderboard_id' => $leaderboard->id,
        'type' => 'badge',
        'event_date' => '2026-06-10',
        'data' => ['student_id' => $this->student->id, 'student_name' => $this->student->name, 'badge_name' => 'حافظ متميز'],
    ]);

    $feed = StudentActivityFeedService::recentActivity($this->student, $leaderboard);

    expect($feed)->toHaveCount(1);
    expect($feed[0]['title'])->toContain('حافظ متميز');
});

it('excludes the student own events from the circle news feed', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة تجريبية',
        'competition_type' => 'points',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);

    GamificationNews::create([
        'leaderboard_id' => $leaderboard->id,
        'type' => 'badge',
        'event_date' => '2026-06-10',
        'data' => ['student_id' => $this->student->id, 'student_name' => $this->student->name, 'badge_name' => 'وسام الطالب'],
    ]);
    GamificationNews::create([
        'leaderboard_id' => $leaderboard->id,
        'type' => 'level_up',
        'event_date' => '2026-06-10',
        'data' => ['student_id' => $this->classmate->id, 'student_name' => $this->classmate->name, 'level_name' => 'حافظ'],
    ]);

    $news = StudentActivityFeedService::circleNews($this->student, $leaderboard);

    expect($news)->toHaveCount(1);
    expect($news[0]['student_name'])->toBe($this->classmate->name);
});

it('returns an empty circle news feed when there is no active leaderboard', function () {
    expect(StudentActivityFeedService::circleNews($this->student, null))->toBe([]);
});
