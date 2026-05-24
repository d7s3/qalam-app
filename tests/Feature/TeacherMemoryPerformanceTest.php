<?php

use App\Livewire\Teacher\Attendance;
use App\Livewire\Teacher\LeaderboardGrade;
use App\Livewire\Teacher\Leaderboards;
use App\Models\AcademicCalendarEvent;
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

test('plan creator verifies academic calendar with multi-period distribution and gaps', function () {
    // 1. Create period A
    AcademicCalendarEvent::create([
        'event_name' => 'الفترة الأولى',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-15',
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4], // Sun, Mon, Tue, Wed
        'is_visible' => true,
    ]);

    // 2. Create period B (starts after a gap of 5 days)
    AcademicCalendarEvent::create([
        'event_name' => 'الفترة الثانية',
        'start_date' => '2026-06-21',
        'end_date' => '2026-07-05',
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5], // Sun, Mon, Tue, Wed, Thu
        'is_visible' => true,
    ]);

    // Test component behavior
    $component = Livewire::test('shared.⚡plan-creator')
        ->set('startDate', '2026-06-01')
        ->set('daysCount', 15)
        ->call('checkAttendancePeriod');

    $component->assertSet('isOutsidePeriod', false)
        ->assertSet('expectedEndDate', '2026-06-24')
        ->assertSet('totalCalendarDays', 24) // June 1 to June 24 is 24 calendar days
        ->assertSet('periodDistribution', [
            'الفترة الأولى' => 9,
            'خارج فترات الدوام' => 2,
            'الفترة الثانية' => 4,
        ]);
});

test('plan creator applies review ceiling on auto fill', function () {
    // Let's create more surahs and ayahs so we can test boundaries
    // Surah 1 has 7 verses.
    for ($i = 2; $i <= 7; $i++) {
        Ayah::create([
            'id' => $i,
            'surah_id' => 1,
            'verse_number' => $i,
            'page_number' => 1,
            'line_number_start' => $i,
            'line_number_end' => $i,
            'verse_key' => "1:$i",
            'juz_number' => 1,
            'hizb_number' => 1,
            'rub_number' => 1,
            'ruku_number' => 1,
            'manzil_number' => 1,
            'text_uthmani' => "Ayah $i of Surah 1",
        ]);
    }

    // Let's create Surah 2 with 5 verses.
    Surah::create([
        'id' => 2,
        'number' => 2,
        'name_arabic' => 'البقرة',
        'name_simple' => 'Al-Baqarah',
        'revelation_place' => 'madinah',
        'revelation_order' => 2,
        'verses_count' => 5,
        'start_page' => 2,
        'end_page' => 2,
    ]);

    for ($i = 1; $i <= 5; $i++) {
        Ayah::create([
            'id' => 15 + $i,
            'surah_id' => 2,
            'verse_number' => $i,
            'page_number' => 2,
            'line_number_start' => $i,
            'line_number_end' => $i,
            'verse_key' => "2:$i",
            'juz_number' => 1,
            'hizb_number' => 1,
            'rub_number' => 1,
            'ruku_number' => 1,
            'manzil_number' => 1,
            'text_uthmani' => "Ayah $i of Surah 2",
        ]);
    }

    // Now test the component
    $component = Livewire::test('shared.⚡plan-creator')
        ->set('studentId', $this->students->first()->id)
        ->set('planType', 'review')
        ->set('fillDirection', 'forward')
        ->set('startDate', '2026-06-01')
        ->set('daysCount', 5)
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
        ->call('generateDays');

    // First: set ceiling to Surah 1, Verse 5, and fill days 0, 1, 2
    $component->set('memorizedUpToSurah', 1)
        ->set('memorizedUpToVerse', 5)
        ->call('fillSelected', 'surah', 'review', [0, 1, 2]);

    // Second: change ceiling to Surah 2, Verse 3, and fill days 3, 4
    $component->set('memorizedUpToSurah', 2)
        ->set('memorizedUpToVerse', 3)
        ->call('fillSelected', 'surah', 'review', [3, 4]);

    // Verify first 3 days respect the first ceiling (Surah 1, Verse 5)
    $planDays = $component->get('planDays');
    for ($i = 0; $i <= 2; $i++) {
        expect($planDays[$i]['review_to_surah_id'])->toBeLessThanOrEqual(1);
        if ($planDays[$i]['review_to_surah_id'] === 1) {
            expect($planDays[$i]['review_to_verse'])->toBeLessThanOrEqual(5);
        }
    }

    // Verify day 3 & 4 respect the second ceiling (Surah 2, Verse 3)
    for ($i = 3; $i <= 4; $i++) {
        expect($planDays[$i]['review_to_surah_id'])->toBeLessThanOrEqual(2);
        if ($planDays[$i]['review_to_surah_id'] === 2) {
            expect($planDays[$i]['review_to_verse'])->toBeLessThanOrEqual(3);
        }
    }
});

test('plan creator supports dynamic custom pages auto fill', function () {
    // We want to test filling 2 pages.
    // Let's create Surah 3 with 40 verses (so we have enough verses to fill pages)
    Surah::create([
        'id' => 3,
        'number' => 3,
        'name_arabic' => 'آل عمران',
        'name_simple' => 'Al-Imran',
        'revelation_place' => 'madinah',
        'revelation_order' => 3,
        'verses_count' => 40,
        'start_page' => 3,
        'end_page' => 5,
    ]);

    for ($i = 1; $i <= 40; $i++) {
        // Let's spread verses across 3 pages: 15 on page 3, 15 on page 4, 10 on page 5
        $page = 3;
        if ($i > 30) {
            $page = 5;
        } elseif ($i > 15) {
            $page = 4;
        }

        $line = $i % 15 ?: 15;

        Ayah::create([
            'id' => 30 + $i,
            'surah_id' => 3,
            'verse_number' => $i,
            'page_number' => $page,
            'line_number_start' => $line,
            'line_number_end' => $line,
            'verse_key' => "3:$i",
            'juz_number' => 1,
            'hizb_number' => 1,
            'rub_number' => 1,
            'ruku_number' => 1,
            'manzil_number' => 1,
            'text_uthmani' => "Ayah $i of Surah 3",
        ]);
    }

    $component = Livewire::test('shared.⚡plan-creator')
        ->set('studentId', $this->students->first()->id)
        ->set('planType', 'hifz')
        ->set('fillDirection', 'forward')
        ->set('startDate', '2026-06-01')
        ->set('daysCount', 3)
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
        ->call('generateDays');

    // Set first day's start to Surah 3, Verse 1
    $component->set('planDays.0.from_surah_id', 3)
        ->set('planDays.0.from_verse', 1);

    // Call custom_pages_2 (2 pages = 30 lines) on the first day
    $component->call('fillSelected', 'custom_pages_2', 'hifz', [0]);

    $planDays = $component->get('planDays');
    // First day should end at Surah 3, Verse 30 (since verse 1 to 15 is 15 lines/page 1, and 16 to 30 is 15 lines/page 2)
    expect($planDays[0]['to_surah_id'])->toBe(3);
    expect($planDays[0]['to_verse'])->toBe(30);
});

test('plan creator applies page alignment rules on review but not on hifz', function () {
    // We want to test page-fill optimization rules for 'review' plan type.
    // Let's create:
    // Surah 4: 30 verses (lines). Ends at page 7, line 10.
    // Surah 5: 10 verses (lines). Ends at page 8, line 5.
    // Surah 6: 30 verses (lines). Ends at page 10, line 5.
    // Let's make them contiguous starting from page 5, line 11 (after Surah 3's end at page 5, line 10).

    $currentPage = 5;
    $currentLine = 11;

    $createContiguousSurah = function (int $surahId, int $versesCount) use (&$currentPage, &$currentLine) {
        Surah::create([
            'id' => $surahId,
            'number' => $surahId,
            'name_arabic' => 'سورة '.$surahId,
            'name_simple' => 'Surah '.$surahId,
            'revelation_place' => 'makkah',
            'revelation_order' => $surahId,
            'verses_count' => $versesCount,
            'start_page' => $currentPage,
            'end_page' => $currentPage + ceil(($currentLine - 1 + $versesCount) / 15) - 1,
        ]);

        for ($v = 1; $v <= $versesCount; $v++) {
            Ayah::create([
                'id' => $surahId * 1000 + $v,
                'surah_id' => $surahId,
                'verse_number' => $v,
                'page_number' => $currentPage,
                'line_number_start' => $currentLine,
                'line_number_end' => $currentLine,
                'verse_key' => "$surahId:$v",
                'juz_number' => 1,
                'hizb_number' => 1,
                'rub_number' => 1,
                'ruku_number' => 1,
                'manzil_number' => 1,
                'text_uthmani' => "Ayah $v of Surah $surahId",
            ]);
            $currentLine++;
            if ($currentLine > 15) {
                $currentLine = 1;
                $currentPage++;
            }
        }
    };

    $createContiguousSurah(4, 30); // Surah 4: 30 verses.
    $createContiguousSurah(5, 10); // Surah 5: 10 verses.
    $createContiguousSurah(6, 30); // Surah 6: 30 verses.

    // Scenario 1: Rule 1 (Surah Completion)
    // Starting at Surah 4, Verse 1. Volume = 1 page (15 lines).
    // Remaining in Surah 4 is 15 lines. Since 15 <= 15, it should complete Surah 4 (ending at Surah 4, Verse 30).

    // First, test with 'review' plan type (should apply optimization)
    $component = Livewire::test('shared.⚡plan-creator')
        ->set('studentId', $this->students->first()->id)
        ->set('planType', 'review')
        ->set('fillDirection', 'forward')
        ->set('startDate', '2026-06-01')
        ->set('daysCount', 3)
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
        ->set('memorizedUpToSurah', 6)
        ->set('memorizedUpToVerse', 30)
        ->call('generateDays');

    $component->set('planDays.0.review_from_surah_id', 4)
        ->set('planDays.0.review_from_verse', 1)
        ->call('fillSelected', 'page', 'review', [0]);

    $planDays = $component->get('planDays');
    expect($planDays[0]['review_to_surah_id'])->toBe(4);
    expect($planDays[0]['review_to_verse'])->toBe(30); // Completed!

    // Second, test with 'hifz' plan type (should NOT apply optimization)
    $componentHifz = Livewire::test('shared.⚡plan-creator')
        ->set('studentId', $this->students->first()->id)
        ->set('planType', 'hifz')
        ->set('fillDirection', 'forward')
        ->set('startDate', '2026-06-01')
        ->set('daysCount', 3)
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
        ->call('generateDays');

    $componentHifz->set('planDays.0.from_surah_id', 4)
        ->set('planDays.0.from_verse', 1)
        ->call('fillSelected', 'page', 'hifz', [0]);

    $planDaysHifz = $componentHifz->get('planDays');
    expect($planDaysHifz[0]['to_surah_id'])->toBe(4);
    expect($planDaysHifz[0]['to_verse'])->toBe(15); // Not completed (exactly 1 page / 15 lines)

    // Scenario 2: Rule 2 Scenario A (New surah <= 15 lines total, complete it)
    // Starting at Surah 4, Verse 20. Volume = 1 page (15 lines).
    // Remaining in Surah 4 is 11 lines. New surah is Surah 5 (10 lines total).
    // Normal traverse ends at Surah 5, Verse 4 (11 + 4 = 15 lines).
    // Since lines in Surah 5 is 4 (< 15) and total size is 10 (<= 15), it should complete Surah 5 (ends at Surah 5, Verse 10).

    // Test with 'review' plan type (should apply optimization)
    $component->set('planDays.0.review_from_surah_id', 4)
        ->set('planDays.0.review_from_verse', 20)
        ->call('fillSelected', 'page', 'review', [0]);

    $planDays = $component->get('planDays');
    expect($planDays[0]['review_to_surah_id'])->toBe(5);
    expect($planDays[0]['review_to_verse'])->toBe(10); // Completed!

    // Test with 'hifz' plan type (should NOT apply optimization)
    $componentHifz->set('planDays.0.from_surah_id', 4)
        ->set('planDays.0.from_verse', 20)
        ->call('fillSelected', 'page', 'hifz', [0]);

    $planDaysHifz = $componentHifz->get('planDays');
    expect($planDaysHifz[0]['to_surah_id'])->toBe(5);
    expect($planDaysHifz[0]['to_verse'])->toBe(4); // Not completed (exactly 15 lines total)

    // Scenario 3: Rule 2 Scenario B (New surah > 15 lines total, read exactly 15 lines of it)
    // Starting at Surah 5, Verse 5. Volume = 1 page (15 lines).
    // Remaining in Surah 5 is 6 lines. New surah is Surah 6 (30 lines total).
    // Normal traverse ends at Surah 6, Verse 9 (6 + 9 = 15 lines).
    // Since lines in Surah 6 is 9 (< 15) and total size is 30 (> 15), it should extend to 15 lines of Surah 6 (ends at Surah 6, Verse 15).

    // Test with 'review' plan type (should apply optimization)
    $component->set('planDays.0.review_from_surah_id', 5)
        ->set('planDays.0.review_from_verse', 5)
        ->call('fillSelected', 'page', 'review', [0]);

    $planDays = $component->get('planDays');
    expect($planDays[0]['review_to_surah_id'])->toBe(6);
    expect($planDays[0]['review_to_verse'])->toBe(15); // Extended to 15 lines of the new surah!

    // Test with 'hifz' plan type (should NOT apply optimization)
    $componentHifz->set('planDays.0.from_surah_id', 5)
        ->set('planDays.0.from_verse', 5)
        ->call('fillSelected', 'page', 'hifz', [0]);

    $planDaysHifz = $componentHifz->get('planDays');
    expect($planDaysHifz[0]['to_surah_id'])->toBe(6);
    expect($planDaysHifz[0]['to_verse'])->toBe(9); // Not completed/extended (exactly 15 lines total)
});
