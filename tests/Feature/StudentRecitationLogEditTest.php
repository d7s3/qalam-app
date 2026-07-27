<?php

use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/**
 * @return array{0: Teacher, 1: Student, 2: StudentPlanDay}
 */
function makeGradedHifzDay(): array
{
    $teacher = Teacher::factory()->create();
    $circle = Circle::factory()->create();
    $circle->teachers()->attach($teacher->id);
    $student = Student::factory()->create(['circle_id' => $circle->id]);

    $plan = StudentPlan::create([
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'start_date' => '2026-07-01',
        'days_count' => 10,
        'active_days' => ['Sunday', 'Monday'],
        'plan_type' => 'hifz_review',
        'status' => 'active',
    ]);

    $day = StudentPlanDay::create([
        'student_plan_id' => $plan->id,
        'date' => '2026-07-05',
        'day_name' => 'الأحد',
        'hifz_achievement' => 3,
        'hifz_graded_at' => '2026-07-08 09:30:00',
    ]);

    return [$teacher, $student, $day];
}

it('lets the owning teacher move a grading date while keeping the time of day', function () {
    [$teacher, $student, $day] = makeGradedHifzDay();

    $this->actingAs($teacher, 'teacher');

    Livewire::test('teacher.student-recitation-log', ['studentId' => $student->id])
        ->set('editKey', "quran:{$day->id}:hifz")
        ->set('editDate', '2026-07-05')
        ->call('saveGradingDate')
        ->assertHasNoErrors();

    $fresh = $day->fresh();

    expect($fresh->hifz_graded_at->format('Y-m-d'))->toBe('2026-07-05')   // date moved
        ->and($fresh->hifz_graded_at->format('H:i:s'))->toBe('09:30:00'); // original time kept
});

it('validates that a grading date is required', function () {
    [$teacher, $student, $day] = makeGradedHifzDay();

    $this->actingAs($teacher, 'teacher');

    Livewire::test('teacher.student-recitation-log', ['studentId' => $student->id])
        ->set('editKey', "quran:{$day->id}:hifz")
        ->set('editDate', '')
        ->call('saveGradingDate')
        ->assertHasErrors('editDate');

    expect($day->fresh()->hifz_graded_at->format('Y-m-d'))->toBe('2026-07-08');
});

it('forbids a teacher who does not own the student from editing the grading date', function () {
    [$owner, $student, $day] = makeGradedHifzDay();
    $intruder = Teacher::factory()->create(); // not attached to the student's circle

    $this->actingAs($intruder, 'teacher');

    try {
        Livewire::test('teacher.student-recitation-log', ['studentId' => $student->id])
            ->set('editKey', "quran:{$day->id}:hifz")
            ->set('editDate', '2026-07-05')
            ->call('saveGradingDate');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    // Whatever the surfaced status, the grading date must stay untouched.
    expect($day->fresh()->hifz_graded_at->format('Y-m-d'))->toBe('2026-07-08');
});
