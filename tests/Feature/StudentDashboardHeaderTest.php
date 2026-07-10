<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\GamificationNews;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
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
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    AcademicCalendarEvent::create([
        'event_name' => 'دوام كامل',
        'start_date' => now()->subDays(30)->format('Y-m-d'),
        'end_date' => now()->addDays(30)->format('Y-m-d'),
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5, 6, 7],
        'is_visible' => true,
    ]);

    $surah = Surah::create([
        'number' => 1,
        'name_arabic' => 'سورة الاختبار',
        'name_simple' => 'Test Surah',
        'revelation_place' => 'makkah',
        'revelation_order' => 1,
        'verses_count' => 1,
        'start_page' => 1,
        'end_page' => 1,
    ]);
    DB::table('ayahs')->insert([
        'id' => 1,
        'surah_id' => $surah->id,
        'verse_number' => 1,
        'verse_key' => '1:1',
        'juz_number' => 1,
        'hizb_number' => 1,
        'rub_number' => 1,
        'page_number' => 1,
        'ruku_number' => 1,
        'manzil_number' => 1,
        'text_uthmani' => 'نص الآية التجريبية',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('renders the desktop topbar with search on the student dashboard', function () {
    $this->actingAs($this->student, 'student');

    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSeeLivewire('student.header-search');
});

it('does not render the student topbar on non-student pages', function () {
    $this->actingAs($this->teacher, 'teacher');

    $this->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertDontSeeLivewire('student.header-search');
});

it('renders the verse-of-the-day panel always, regardless of gamification state', function () {
    $this->actingAs($this->student, 'student');

    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSee('نص الآية التجريبية');

    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة تجريبية',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSee('نص الآية التجريبية');
});

it('shows an empty state in the community section when there is no active leaderboard', function () {
    $this->actingAs($this->student, 'student');

    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSee('لا توجد أنشطة من زملاء الحلقة بعد');
});

it('shows real circle-mate events in the community section', function () {
    $this->actingAs($this->student, 'student');

    $classmate = Student::factory()->create(['circle_id' => $this->circle->id]);

    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة تجريبية',
        'competition_type' => 'points',
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(2),
        'is_active' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach($this->circle->id);

    GamificationNews::create([
        'leaderboard_id' => $leaderboard->id,
        'type' => 'badge',
        'event_date' => '2026-07-07',
        'data' => ['student_id' => $classmate->id, 'student_name' => $classmate->name, 'badge_name' => 'حافظ متميز'],
    ]);

    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSee($classmate->name)
        ->assertSee('حافظ متميز')
        ->assertDontSee('لا توجد أنشطة من زملاء الحلقة بعد');
});
