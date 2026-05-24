<?php

use App\Livewire\Teacher\Attendance;
use App\Livewire\Teacher\LeaderboardGrade;
use App\Livewire\Teacher\Leaderboards;
use App\Models\Ayah;
use App\Models\Circle;
use App\Models\Leaderboard;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Surah;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create dummy Surah and Ayah for plans eager loading
    Surah::create([
        'id' => 1,
        'number' => 1,
        'name_arabic' => 'الفاتحة',
        'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah',
        'revelation_order' => 1,
        'verses_count' => 7,
        'start_page' => 1,
        'end_page' => 1,
    ]);

    Ayah::create([
        'id' => 1,
        'surah_id' => 1,
        'verse_number' => 1,
        'page_number' => 1,
        'line_number_start' => 1,
        'line_number_end' => 1,
        'verse_key' => '1:1',
        'juz_number' => 1,
        'hizb_number' => 1,
        'rub_number' => 1,
        'ruku_number' => 1,
        'manzil_number' => 1,
        'text_uthmani' => 'Ayah 1 text',
    ]);

    // Setup teacher, circle, 20 students, and plans/days
    $this->teacher = Teacher::factory()->create();
    $this->circle = Circle::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->students = Student::factory()->count(20)->create([
        'circle_id' => $this->circle->id,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $today = now();
    foreach ($this->students as $student) {
        $plan = StudentPlan::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'start_date' => $today->copy()->subDays(5),
            'days_count' => 30,
            'active_days' => [0, 1, 2, 3, 4, 5, 6],
            'description' => 'Test Plan',
            'status' => 'active',
            'plan_type' => 'hifz_review',
            'direction' => 'forward',
            'is_approved' => true,
            'created_by_role' => 'teacher',
        ]);

        for ($i = 0; $i < 30; $i++) {
            StudentPlanDay::create([
                'student_plan_id' => $plan->id,
                'date' => $today->copy()->subDays(5)->addDays($i)->format('Y-m-d'),
                'day_name' => $today->copy()->subDays(5)->addDays($i)->dayName,
                'from_ayah_id' => 1,
                'to_ayah_id' => 1,
                'review_from_ayah_id' => 1,
                'review_to_ayah_id' => 1,
                'hifz_achievement' => $i < 5 ? 100 : null,
                'review_achievement' => $i < 5 ? 100 : null,
            ]);
        }
    }

    $this->actingAs($this->teacher, 'teacher');
});

test('teacher tasmeeh-manager renders efficiently within budgets', function () {
    gc_collect_cycles();
    $memBefore = memory_get_usage();

    DB::enableQueryLog();
    Livewire::test('teacher.⚡tasmeeh-manager');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    gc_collect_cycles();
    $memAfter = memory_get_usage();

    $queryCount = count($queries);
    $memUsed = $memAfter - $memBefore;

    // Assert optimized queries (budget is < 20 queries, usually 12-15)
    expect($queryCount)->toBeLessThan(20);
    // Assert memory allocation is low (budget is 15MB)
    expect($memUsed)->toBeLessThan(15 * 1024 * 1024);
});

test('teacher attendance component renders efficiently within budgets', function () {
    gc_collect_cycles();
    $memBefore = memory_get_usage();

    DB::enableQueryLog();
    Livewire::test(Attendance::class);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    gc_collect_cycles();
    $memAfter = memory_get_usage();

    $queryCount = count($queries);
    $memUsed = $memAfter - $memBefore;

    // Attendance component renders lists of students. It should execute few queries (budget < 15 queries)
    expect($queryCount)->toBeLessThan(15);
    expect($memUsed)->toBeLessThan(15 * 1024 * 1024);
});

test('teacher student-manager component renders efficiently within budgets', function () {
    gc_collect_cycles();
    $memBefore = memory_get_usage();

    DB::enableQueryLog();
    Livewire::test('teacher.⚡student-manager');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    gc_collect_cycles();
    $memAfter = memory_get_usage();

    $queryCount = count($queries);
    $memUsed = $memAfter - $memBefore;

    // Student manager component lists circle students.
    expect($queryCount)->toBeLessThan(15);
    expect($memUsed)->toBeLessThan(15 * 1024 * 1024);
});

test('shared plan-creator component renders efficiently within budgets', function () {
    gc_collect_cycles();
    $memBefore = memory_get_usage();

    DB::enableQueryLog();
    Livewire::test('shared.⚡plan-creator');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    gc_collect_cycles();
    $memAfter = memory_get_usage();

    $queryCount = count($queries);
    $memUsed = $memAfter - $memBefore;

    // Plan creator should render with minimal queries.
    expect($queryCount)->toBeLessThan(15);
    expect($memUsed)->toBeLessThan(15 * 1024 * 1024);
});

test('teacher leaderboards component renders efficiently within budgets', function () {
    gc_collect_cycles();
    $memBefore = memory_get_usage();

    DB::enableQueryLog();
    Livewire::test(Leaderboards::class);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    gc_collect_cycles();
    $memAfter = memory_get_usage();

    $queryCount = count($queries);
    $memUsed = $memAfter - $memBefore;

    // Leaderboards list circle leaderboards.
    expect($queryCount)->toBeLessThan(15);
    expect($memUsed)->toBeLessThan(15 * 1024 * 1024);
});

test('teacher student-manager dispatches student-list-updated event on student creation', function () {
    $teacher = $this->teacher;
    $permissions = $teacher->permissions ?? [];
    $permissions['can_create_students'] = true;
    $teacher->permissions = $permissions;
    $teacher->save();

    Livewire::test('teacher.⚡student-manager')
        ->set('name', 'New Student')
        ->set('phone', '0500000000')
        ->call('createStudent')
        ->assertDispatched('student-list-updated');
});

test('teacher attendance component listens to student-list-updated event', function () {
    Livewire::test(Attendance::class)
        ->dispatch('student-list-updated')
        ->assertStatus(200);
});

test('teacher tasmeeh-manager component listens to student-list-updated event', function () {
    Livewire::test('teacher.⚡tasmeeh-manager')
        ->dispatch('student-list-updated')
        ->assertStatus(200);
});

test('teacher leaderboard-grade component listens to student-list-updated event', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'Test Leaderboard',
        'is_active' => true,
        'start_date' => now(),
        'end_date' => now()->addDays(7),
    ]);

    Livewire::test(LeaderboardGrade::class, ['leaderboardId' => $leaderboard->id])
        ->dispatch('student-list-updated')
        ->assertStatus(200);
});
