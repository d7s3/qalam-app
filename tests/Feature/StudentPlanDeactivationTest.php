<?php

use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = Teacher::factory()->create();
    $this->circle = Circle::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $this->plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => now()->subDays(2),
        'days_count' => 5,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'description' => 'خطة الحفظ',
        'status' => 'active',
        'plan_type' => 'hifz',
        'direction' => 'forward',
        'is_approved' => true,
        'created_by_role' => 'teacher',
    ]);

    $this->actingAs($this->teacher, 'teacher');
});

it('lets the teacher deactivate and reactivate a plan from the plans list', function () {
    Livewire::test('teacher.⚡student-plans-list')
        ->call('togglePlanStatus', $this->plan->id);

    expect($this->plan->refresh()->status)->toBe('inactive');

    Livewire::test('teacher.⚡student-plans-list')
        ->call('togglePlanStatus', $this->plan->id);

    expect($this->plan->refresh()->status)->toBe('active');
});

it('does not let a teacher toggle plans of students outside their circles', function () {
    $otherCircle = Circle::factory()->create();
    $otherStudent = Student::factory()->create([
        'circle_id' => $otherCircle->id,
        'status' => 'active',
        'is_approved' => true,
    ]);
    $otherPlan = StudentPlan::create([
        'student_id' => $otherStudent->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => now(),
        'days_count' => 5,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'status' => 'active',
        'plan_type' => 'hifz',
        'direction' => 'forward',
        'is_approved' => true,
        'created_by_role' => 'teacher',
    ]);

    expect(fn () => Livewire::test('teacher.⚡student-plans-list')->call('togglePlanStatus', $otherPlan->id))
        ->toThrow(ModelNotFoundException::class);

    expect($otherPlan->refresh()->status)->toBe('active');
});

it('hides inactive plans from the teacher tasmeeh manager', function () {
    $component = Livewire::test('teacher.⚡tasmeeh-manager');
    expect($component->viewData('studentsWithPlansPresent')->pluck('id')->all())
        ->toContain($this->student->id);

    $this->plan->update(['status' => 'inactive']);

    $component = Livewire::test('teacher.⚡tasmeeh-manager');
    expect($component->viewData('studentsWithPlansPresent')->pluck('id')->all())
        ->not->toContain($this->student->id);
    expect($component->viewData('studentsWithoutPlans')->pluck('id')->all())
        ->toContain($this->student->id);
});

it('never selects an inactive plan in the student tasmeeh card', function () {
    $this->plan->update(['status' => 'inactive']);

    $sPlans = StudentPlan::where('student_id', $this->student->id)->latest()->get();

    Livewire::test('teacher.⚡student-tasmeeh-card', [
        'student' => $this->student,
        'sPlans' => $sPlans,
        'activePlanId' => $this->plan->id,
    ])
        ->assertOk()
        ->assertViewHas('activePlan', null);
});
