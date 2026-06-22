<?php

use App\Livewire\Shared\HadithPlanCreator;
use App\Models\Ayah;
use App\Models\Circle;
use App\Models\Hadith;
use App\Models\HadithChapter;
use App\Models\HadithLine;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentHadithPlan;
use App\Models\StudentHadithPlanDay;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Supervisor;
use App\Models\Surah;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'مرحلة الخطط']);
    $this->circle = Circle::create(['name' => 'حلقة الخطط', 'stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::create([
        'name' => 'طالب الخطة',
        'email' => 'planstudent@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    $this->chapter = HadithChapter::create(['name' => 'كتاب الإيمان']);
    $this->hadith = Hadith::create([
        'hadith_chapter_id' => $this->chapter->id,
        'name' => 'الأعمال بالنيات',
        'sanad' => 'عمر بن الخطاب',
        'ruling' => 'صحيح',
    ]);

    // Create 15 lines
    for ($i = 1; $i <= 15; $i++) {
        HadithLine::create([
            'hadith_id' => $this->hadith->id,
            'line_number' => $i,
            'text' => "نص السطر {$i}",
        ]);
    }
});

it('allows supervisor to create a student hadith plan', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(HadithPlanCreator::class)
        ->set('studentId', $this->student->id)
        ->set('hadithId', $this->hadith->id)
        ->set('startDate', '2026-06-18')
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday'])
        ->set('hifzStart', 1)
        ->set('hifzEnd', 10)
        ->set('hifzRate', 2) // 5 days
        ->set('hasReview', true)
        ->set('reviewStart', 11)
        ->set('reviewEnd', 15)
        ->set('reviewRate', 1) // 5 days
        ->call('generatePreview')
        ->assertHasNoErrors()
        ->call('savePlan')
        ->assertHasNoErrors();

    $plan = StudentHadithPlan::where('student_id', $this->student->id)->where('hadith_id', $this->hadith->id)->first();
    expect($plan)->not->toBeNull();
    expect($plan->status)->toBe('active');
    expect($plan->created_by_role)->toBe('supervisor');

    $days = StudentHadithPlanDay::where('student_hadith_plan_id', $plan->id)->orderBy('date')->get();
    expect($days)->toHaveCount(5);
    expect($days[0]->from_line_number)->toBe(1);
    expect($days[0]->to_line_number)->toBe(2);
    expect($days[0]->review_from_line_number)->toBe(11);
    expect($days[0]->review_to_line_number)->toBe(11);
});

it('allows teacher to create a student hadith plan', function () {
    $this->actingAs($this->teacher, 'teacher');

    Livewire::test(HadithPlanCreator::class)
        ->set('studentId', $this->student->id)
        ->set('hadithId', $this->hadith->id)
        ->set('startDate', '2026-06-18')
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday'])
        ->set('hifzStart', 1)
        ->set('hifzEnd', 12)
        ->set('hifzRate', 3) // 4 days
        ->call('generatePreview')
        ->assertHasNoErrors()
        ->call('savePlan')
        ->assertHasNoErrors();

    $plan = StudentHadithPlan::where('student_id', $this->student->id)->where('hadith_id', $this->hadith->id)->first();
    expect($plan)->not->toBeNull();
    expect($plan->created_by_role)->toBe('teacher');

    $days = StudentHadithPlanDay::where('student_hadith_plan_id', $plan->id)->orderBy('date')->get();
    expect($days)->toHaveCount(4);
});

it('allows teacher to grade student recitation of hadith plan days', function () {
    $this->actingAs($this->teacher, 'teacher');

    $plan = StudentHadithPlan::create([
        'student_id' => $this->student->id,
        'hadith_id' => $this->hadith->id,
        'start_date' => '2026-06-18',
        'status' => 'active',
        'created_by_role' => 'teacher',
    ]);

    $day = StudentHadithPlanDay::create([
        'student_hadith_plan_id' => $plan->id,
        'date' => '2026-06-18',
        'day_name' => 'الخميس',
        'from_line_number' => 1,
        'to_line_number' => 5,
        'review_from_line_number' => 6,
        'review_to_line_number' => 10,
    ]);

    // Test grading Hifz
    Livewire::test('teacher.student-tasmeeh-card', [
        'student' => $this->student,
        'sPlans' => collect(),
        'activePlanId' => null,
        'gradedAtDate' => '2026-06-18',
    ])
        ->call('saveHadithAchievement', $day->id, 'hifz', 3) // Excellent
        ->assertHasNoErrors();

    expect($day->fresh()->hifz_achievement)->toBe(3);
    expect($day->fresh()->hifz_graded_at)->not->toBeNull();

    // Test grading Review
    Livewire::test('teacher.student-tasmeeh-card', [
        'student' => $this->student,
        'sPlans' => collect(),
        'activePlanId' => null,
        'gradedAtDate' => '2026-06-18',
    ])
        ->call('saveHadithAchievement', $day->id, 'review', 2) // Good
        ->assertHasNoErrors();

    expect($day->fresh()->review_achievement)->toBe(2);
    expect($day->fresh()->review_graded_at)->not->toBeNull();
});

it('renders hadith plans list page for supervisor', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $response = $this->get(route('supervisor.hadiths.plans'));
    $response->assertSuccessful();
    $response->assertSee('خطط الأحاديث المنشأة');
});

it('renders hadith plans list page for teacher', function () {
    $this->actingAs($this->teacher, 'teacher');

    $response = $this->get(route('teacher.hadith-plans'));
    $response->assertSuccessful();
    $response->assertSee('خطط الأحاديث المنشأة');
});

it('allows supervisor and teacher to delete hadith plans', function () {
    $this->actingAs($this->teacher, 'teacher');

    $plan = StudentHadithPlan::create([
        'student_id' => $this->student->id,
        'hadith_id' => $this->hadith->id,
        'start_date' => '2026-06-18',
        'status' => 'active',
        'created_by_role' => 'teacher',
    ]);

    Livewire::test('shared.hadith-plans-list', ['role' => 'teacher'])
        ->call('deletePlan', $plan->id)
        ->assertHasNoErrors();

    expect(StudentHadithPlan::where('id', $plan->id)->exists())->toBeFalse();
});

it('renders both Quranic plan and Hadith plan simultaneously in student-tasmeeh-card', function () {
    $this->actingAs($this->teacher, 'teacher');

    // Create dummy Surah and Ayah for database constraints
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

    // Create a Quranic plan
    $quranicPlan = StudentPlan::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => '2026-06-18',
        'days_count' => 5,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'status' => 'active',
        'plan_type' => 'hifz',
        'direction' => 'forward',
        'is_approved' => true,
        'created_by_role' => 'teacher',
    ]);

    // Create Quranic days
    for ($i = 0; $i < 5; $i++) {
        StudentPlanDay::create([
            'student_plan_id' => $quranicPlan->id,
            'date' => Carbon::parse('2026-06-18')->addDays($i)->format('Y-m-d'),
            'day_name' => 'الأحد',
            'from_ayah_id' => 1,
            'to_ayah_id' => 1,
        ]);
    }

    // Create a Hadith plan
    $hadithPlan = StudentHadithPlan::create([
        'student_id' => $this->student->id,
        'hadith_id' => $this->hadith->id,
        'start_date' => '2026-06-18',
        'status' => 'active',
        'created_by_role' => 'teacher',
    ]);

    // Create a Hadith day
    $hadithDay = StudentHadithPlanDay::create([
        'student_hadith_plan_id' => $hadithPlan->id,
        'date' => '2026-06-18',
        'day_name' => 'الخميس',
        'from_line_number' => 1,
        'to_line_number' => 5,
    ]);

    // Instantiate card component and check it loads both
    $component = Livewire::test('teacher.student-tasmeeh-card', [
        'student' => $this->student,
        'sPlans' => collect([$quranicPlan]),
        'activePlanId' => $quranicPlan->id,
        'gradedAtDate' => '2026-06-18',
    ]);

    // Quranic plan should be selected in the selector
    expect($component->get('selectedPlanId'))->toBe($quranicPlan->id);

    // Both plans should be returned to the view with Alpine mapping variables
    $viewData = $component->instance()->with();
    $firstQuranDay = StudentPlanDay::where('student_plan_id', $quranicPlan->id)->orderBy('date')->first();

    $expectedQuranMap = [];
    $allQuranDays = StudentPlanDay::where('student_plan_id', $quranicPlan->id)->orderBy('date')->get();
    foreach ($allQuranDays as $day) {
        $expectedQuranMap[$day->date->toDateString()] = $day->id;
    }

    expect($viewData['activePlan']->id)->toBe($quranicPlan->id)
        ->and($viewData['activeHadithPlan']->id)->toBe($hadithPlan->id)
        ->and($viewData['hadithDayForSelectedDate']->id)->toBe($hadithDay->id)
        ->and($viewData['hadithDayIds'])->toBe([$hadithDay->id])
        ->and($viewData['defaultHadithDayId'])->toBe($hadithDay->id)
        ->and($viewData['hadithDateToDayIdMap'])->toBe(['2026-06-18' => $hadithDay->id])
        ->and($viewData['quranDateToDayIdMap'])->toBe($expectedQuranMap);
});
