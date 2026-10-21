<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The plan creator writes plans for the students its reader reaches: a
 * teacher his cohorts', a student only his own. The plan and the student come
 * from the address, so both are asked about again.
 */
function creatorPlanDays(): array
{
    return [[
        'date' => '2026-09-13',
        'day_name_ar' => 'الأحد',
        'from_surah_id' => 1, 'from_verse' => 1, 'to_surah_id' => 1, 'to_verse' => 7,
    ]];
}

beforeEach(function () {
    $stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $stage->id]);
    $otherCircle = Circle::factory()->create(['stage_id' => $stage->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['circle_id' => $this->circle->id, 'status' => 'active']);
    $this->stranger = Student::factory()->create(['circle_id' => $otherCircle->id, 'status' => 'active']);

    $this->strangersPlan = StudentPlan::create([
        'student_id' => $this->stranger->id,
        'start_date' => '2026-09-01',
        'days_count' => 1,
        'active_days' => ['Sunday'],
        'plan_type' => 'hifz',
        'status' => 'active',
        'is_approved' => true,
        'created_by_role' => 'teacher',
        'direction' => 'reverse',
        'review_direction' => 'reverse',
    ]);
    $this->strangersPlan->days()->create(['date' => '2026-09-01', 'day_name' => 'الثلاثاء']);
});

it('does not open another cohort\'s plan to edit', function () {
    $this->actingAs($this->teacher, 'teacher');

    expect(fn () => Livewire::test('shared.plan-creator', ['edit' => $this->strangersPlan->id]))
        ->toThrow(ModelNotFoundException::class);

    expect($this->strangersPlan->days()->count())->toBe(1);
});

it('writes a plan only for a student the teacher teaches', function () {
    $this->actingAs($this->teacher, 'teacher');

    expect(fn () => Livewire::test('shared.plan-creator')
        ->set('studentId', $this->stranger->id)
        ->set('planType', 'hifz')
        ->set('planDays', creatorPlanDays())
        ->call('save'))
        ->toThrow(ModelNotFoundException::class);

    expect(StudentPlan::where('student_id', $this->stranger->id)->count())->toBe(1);

    Livewire::test('shared.plan-creator')
        ->set('studentId', $this->student->id)
        ->set('planType', 'hifz')
        ->set('planDays', creatorPlanDays())
        ->call('save');

    expect((bool) StudentPlan::where('student_id', $this->student->id)->value('is_approved'))->toBeTrue();
});

it('lets a student write only his own plan, and only for approval', function () {
    $this->actingAs($this->student, 'student');

    Livewire::test('shared.plan-creator')
        ->set('userLevel', 'teacher');
})->throws(CannotUpdateLockedPropertyException::class);

it('keeps a student\'s plan to himself', function () {
    $this->actingAs($this->student, 'student');

    expect(fn () => Livewire::test('shared.plan-creator')
        ->set('studentId', $this->stranger->id)
        ->set('planType', 'hifz')
        ->set('planDays', creatorPlanDays())
        ->call('save'))
        ->toThrow(ModelNotFoundException::class);

    expect(StudentPlan::where('student_id', $this->stranger->id)->count())->toBe(1);
});

it('approves a student\'s own plan the moment he saves it', function () {
    // Decided with Faisal on 17 Oct 2026: the student makes his plan and it
    // stands; nobody has to approve it first.
    $this->actingAs($this->student, 'student');

    Livewire::test('shared.plan-creator')
        ->set('planType', 'hifz')
        ->set('planDays', creatorPlanDays())
        ->call('save')
        ->assertRedirect(route('student.plan'));

    $plan = StudentPlan::where('student_id', $this->student->id)->sole();

    expect((bool) $plan->is_approved)->toBeTrue()
        ->and($plan->created_by_role)->toBe('student');
});

it('lets a student withdraw a plan of his own even once he has begun it', function () {
    $this->actingAs($this->student, 'student');
    $plan = StudentPlan::create([
        'student_id' => $this->student->id, 'start_date' => '2026-09-13', 'days_count' => 1, 'active_days' => ['Sunday'],
        'plan_type' => 'hifz', 'status' => 'active', 'is_approved' => true, 'created_by_role' => 'student',
        'direction' => 'reverse', 'review_direction' => 'reverse',
    ]);
    $plan->days()->create(['date' => '2026-09-13', 'day_name' => 'الأحد', 'hifz_achievement' => 3]);

    Livewire::test('student.my-plan')->call('deletePlan', $plan->id);

    expect($plan->fresh())->toBeNull()
        ->and(StudentPlanDay::where('student_plan_id', $plan->id)->exists())->toBeFalse();
});

it('keeps a plan his teacher wrote out of his hands', function () {
    $this->actingAs($this->student, 'student');
    $plan = StudentPlan::create([
        'student_id' => $this->student->id, 'start_date' => '2026-09-13', 'days_count' => 1, 'active_days' => ['Sunday'],
        'plan_type' => 'hifz', 'status' => 'active', 'is_approved' => true, 'created_by_role' => 'teacher',
        'direction' => 'reverse', 'review_direction' => 'reverse',
    ]);

    Livewire::test('student.my-plan')->call('deletePlan', $plan->id);

    expect($plan->fresh())->not->toBeNull();
});
