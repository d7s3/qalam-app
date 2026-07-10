<?php

use App\Models\Ayah;
use App\Models\Circle;
use App\Models\ExamLevel;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Surah;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Smoke-tests every teacher-facing page: seeds a realistic dataset, then
 * hits every named `teacher.*` GET route (including the SPA app-shell tabs
 * and parameterized report/print routes) and asserts it renders
 * successfully.
 */
it('loads every teacher page without error', function () {
    Surah::create([
        'id' => 1, 'number' => 1, 'name_arabic' => 'الفاتحة', 'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah', 'revelation_order' => 1, 'verses_count' => 7,
        'start_page' => 1, 'end_page' => 1,
    ]);
    Ayah::create([
        'id' => 1, 'surah_id' => 1, 'verse_number' => 1, 'page_number' => 1,
        'line_number_start' => 1, 'line_number_end' => 1, 'verse_key' => '1:1',
        'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1, 'ruku_number' => 1,
        'manzil_number' => 1, 'text_uthmani' => 'بسم الله',
    ]);

    $teacher = Teacher::factory()->create(['is_approved' => true]);

    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'حلقة النور', 'stage_id' => $stage->id]);
    $teacher->circles()->attach($circle->id);

    $student = Student::factory()->create(['circle_id' => $circle->id, 'is_approved' => true]);

    $plan = StudentPlan::create([
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'start_date' => now()->subDays(2),
        'days_count' => 5,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'description' => 'خطة تجريبية',
        'status' => 'active',
        'plan_type' => 'hifz_review',
        'direction' => 'forward',
        'is_approved' => true,
        'created_by_role' => 'teacher',
    ]);

    StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => now()->format('Y-m-d'),
        'day_name' => now()->dayName,
        'from_ayah_id' => 1,
        'to_ayah_id' => 1,
        'review_from_ayah_id' => 1,
        'review_to_ayah_id' => 1,
    ]);

    $examLevel = ExamLevel::create(['name' => 'مستوى أول']);
    StudentExam::create([
        'student_id' => $student->id,
        'exam_level_id' => $examLevel->id,
        'date_time' => now()->addDay(),
        'status' => 'pending',
    ]);

    $leaderboard = Leaderboard::create([
        'circle_id' => $circle->id,
        'title' => 'مسابقة تجريبية',
        'competition_type' => 'normal',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
        'is_active_for_grading' => true,
        'settings' => [],
    ]);

    $this->actingAs($teacher, 'teacher');

    $simpleRoutes = [
        'teacher.dashboard',
        'teacher.attendance',
        'teacher.students',
        'teacher.plan-creator',
        'teacher.tasmeeh',
        'teacher.leaderboards',
        'teacher.grade-items',
        'teacher.discipline',
        'teacher.quranic-discipline',
        'teacher.student-plans',
        'teacher.ode-plans',
        'teacher.exceeded-limits',
        'teacher.pairs',
        'teacher.student-exams',
        'teacher.messages',
    ];

    foreach ($simpleRoutes as $routeName) {
        $this->get(route($routeName))->assertSuccessful();
    }

    $this->get(route('teacher.student-recitation-log', $student->id))->assertSuccessful();
    $this->get(route('teacher.leaderboards.grade', $leaderboard->id))->assertSuccessful();
    $this->get(route('teacher.leaderboards.report', $leaderboard->id))->assertSuccessful();
    $this->get(route('teacher.print-plan', $plan->id))->assertSuccessful();
    $this->get(route('teacher.download-plan-pdf', $plan->id))->assertSuccessful();
});
