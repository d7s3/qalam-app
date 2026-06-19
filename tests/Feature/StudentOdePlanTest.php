<?php

use App\Livewire\Shared\OdePlanCreator;
use App\Models\Ayah;
use App\Models\Circle;
use App\Models\Ode;
use App\Models\OdeVerse;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentOdePlan;
use App\Models\StudentOdePlanDay;
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

    $this->ode = Ode::create(['name' => 'تحفة الأطفال']);
    // Create 15 verses
    for ($i = 1; $i <= 15; $i++) {
        OdeVerse::create([
            'ode_id' => $this->ode->id,
            'verse_number' => $i,
            'sadr' => "الصدر {$i}",
            'ajuz' => "العجز {$i}",
        ]);
    }
});

it('allows supervisor to create a student ode plan', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(OdePlanCreator::class)
        ->set('studentId', $this->student->id)
        ->set('odeId', $this->ode->id)
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

    $plan = StudentOdePlan::where('student_id', $this->student->id)->where('ode_id', $this->ode->id)->first();
    expect($plan)->not->toBeNull();
    expect($plan->status)->toBe('active');
    expect($plan->created_by_role)->toBe('supervisor');

    $days = StudentOdePlanDay::where('student_ode_plan_id', $plan->id)->orderBy('date')->get();
    expect($days)->toHaveCount(5);
    expect($days[0]->from_verse_number)->toBe(1);
    expect($days[0]->to_verse_number)->toBe(2);
    expect($days[0]->review_from_verse_number)->toBe(11);
    expect($days[0]->review_to_verse_number)->toBe(11);
});

it('allows teacher to create a student ode plan', function () {
    $this->actingAs($this->teacher, 'teacher');

    Livewire::test(OdePlanCreator::class)
        ->set('studentId', $this->student->id)
        ->set('odeId', $this->ode->id)
        ->set('startDate', '2026-06-18')
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday'])
        ->set('hifzStart', 1)
        ->set('hifzEnd', 12)
        ->set('hifzRate', 3) // 4 days
        ->call('generatePreview')
        ->assertHasNoErrors()
        ->call('savePlan')
        ->assertHasNoErrors();

    $plan = StudentOdePlan::where('student_id', $this->student->id)->where('ode_id', $this->ode->id)->first();
    expect($plan)->not->toBeNull();
    expect($plan->created_by_role)->toBe('teacher');

    $days = StudentOdePlanDay::where('student_ode_plan_id', $plan->id)->orderBy('date')->get();
    expect($days)->toHaveCount(4);
});

it('allows teacher to grade student recitation of ode plan days', function () {
    $this->actingAs($this->teacher, 'teacher');

    $plan = StudentOdePlan::create([
        'student_id' => $this->student->id,
        'ode_id' => $this->ode->id,
        'start_date' => '2026-06-18',
        'status' => 'active',
        'created_by_role' => 'teacher',
    ]);

    $day = StudentOdePlanDay::create([
        'student_ode_plan_id' => $plan->id,
        'date' => '2026-06-18',
        'day_name' => 'الخميس',
        'from_verse_number' => 1,
        'to_verse_number' => 5,
        'review_from_verse_number' => 6,
        'review_to_verse_number' => 10,
    ]);

    // Test grading Hifz
    Livewire::test('teacher.student-tasmeeh-card', [
        'student' => $this->student,
        'sPlans' => collect(),
        'activePlanId' => null,
        'gradedAtDate' => '2026-06-18',
    ])
        ->call('saveOdeAchievement', $day->id, 'hifz', 3) // Excellent
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
        ->call('saveOdeAchievement', $day->id, 'review', 2) // Good
        ->assertHasNoErrors();

    expect($day->fresh()->review_achievement)->toBe(2);
    expect($day->fresh()->review_graded_at)->not->toBeNull();
});

it('renders odes plans list page for supervisor', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $response = $this->get(route('supervisor.odes.plans'));
    $response->assertSuccessful();
    $response->assertSee('خطط المنظومات المنشأة');
});

it('renders odes plans list page for teacher', function () {
    $this->actingAs($this->teacher, 'teacher');

    $response = $this->get(route('teacher.ode-plans'));
    $response->assertSuccessful();
    $response->assertSee('خطط المنظومات المنشأة');
});

it('allows supervisor and teacher to delete ode plans', function () {
    $this->actingAs($this->teacher, 'teacher');

    $plan = StudentOdePlan::create([
        'student_id' => $this->student->id,
        'ode_id' => $this->ode->id,
        'start_date' => '2026-06-18',
        'status' => 'active',
        'created_by_role' => 'teacher',
    ]);

    Livewire::test('shared.ode-plans-list', ['role' => 'teacher'])
        ->call('deletePlan', $plan->id)
        ->assertHasNoErrors();

    expect(StudentOdePlan::where('id', $plan->id)->exists())->toBeFalse();
});

it('renders both Quranic plan and Ode plan simultaneously in student-tasmeeh-card', function () {
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

    // Create an Ode plan
    $odePlan = StudentOdePlan::create([
        'student_id' => $this->student->id,
        'ode_id' => $this->ode->id,
        'start_date' => '2026-06-18',
        'status' => 'active',
        'created_by_role' => 'teacher',
    ]);

    // Create an Ode day
    $odeDay = StudentOdePlanDay::create([
        'student_ode_plan_id' => $odePlan->id,
        'date' => '2026-06-18',
        'day_name' => 'الخميس',
        'from_verse_number' => 1,
        'to_verse_number' => 5,
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
        ->and($viewData['activeOdePlan']->id)->toBe($odePlan->id)
        ->and($viewData['odeDayForSelectedDate']->id)->toBe($odeDay->id)
        ->and($viewData['odeDayIds'])->toBe([$odeDay->id])
        ->and($viewData['defaultOdeDayId'])->toBe($odeDay->id)
        ->and($viewData['odeDateToDayIdMap'])->toBe(['2026-06-18' => $odeDay->id])
        ->and($viewData['quranDateToDayIdMap'])->toBe($expectedQuranMap);
});
