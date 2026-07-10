<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\GamificationTeam;
use App\Models\GamificationTeamTask;
use App\Models\GamificationTeamTaskAssignment;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Surah;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-07-07 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    AcademicCalendarEvent::create([
        'event_name' => 'دوام كامل',
        'start_date' => now()->subDays(30)->format('Y-m-d'),
        'end_date' => now()->addDays(30)->format('Y-m-d'),
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5, 6, 7],
        'is_visible' => true,
    ]);

    $this->actingAs($this->student, 'student');
});

it('shows honest empty states for stats, activity, and assignments for a new student', function () {
    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSee('لا توجد بيانات حفظ لهذا الشهر بعد')
        ->assertSee('ستظهر أنشطتك هنا بمجرد أن تبدأ رحلتك')
        ->assertSee('لا توجد واجبات حالياً');
});

it('shows monthly stats and recent activity from real grading data', function () {
    $surah = Surah::create([
        'number' => 1,
        'name_arabic' => 'الفاتحة',
        'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah',
        'revelation_order' => 1,
        'verses_count' => 7,
        'start_page' => 1,
        'end_page' => 1,
    ]);
    foreach (range(1, 7) as $id) {
        DB::table('ayahs')->insert([
            'id' => $id,
            'surah_id' => $surah->id,
            'verse_number' => $id,
            'verse_key' => "1:{$id}",
            'juz_number' => 1,
            'hizb_number' => 1,
            'rub_number' => 1,
            'page_number' => 1,
            'ruku_number' => 1,
            'manzil_number' => 1,
            'text_uthmani' => 'نص',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'plan_type' => 'hifz',
        'direction' => 'forward',
        'is_approved' => true,
        'start_date' => now()->subDays(2),
        'days_count' => 30,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => today(),
        'day_name' => 'الثلاثاء',
        'hifz_achievement' => 3,
        'hifz_graded_at' => now(),
        'from_ayah_id' => 1,
        'to_ayah_id' => 7,
    ]);

    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('لا توجد بيانات حفظ لهذا الشهر بعد')
        ->assertDontSee('ستظهر أنشطتك هنا بمجرد أن تبدأ رحلتك')
        ->assertSee('حفظت الفاتحة');
});

it('shows a real team task assignment with a countdown', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة تجريبية',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(10),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $team = GamificationTeam::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'فريق النور',
        'color' => '#7a2727',
    ]);
    $team->students()->attach($this->student->id);

    $task = GamificationTeamTask::create([
        'leaderboard_id' => $leaderboard->id,
        'name' => 'حفظ جزء عم كاملاً',
        'xp_reward' => 50,
        'coins_reward' => 10,
    ]);

    GamificationTeamTaskAssignment::create([
        'team_task_id' => $task->id,
        'team_id' => $team->id,
        'start_date' => now(),
        'end_date' => now()->addDays(3),
        'status' => 'assigned',
    ]);

    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSee('حفظ جزء عم كاملاً')
        ->assertSee('+50 XP')
        ->assertDontSee('لا توجد واجبات حالياً');
});
