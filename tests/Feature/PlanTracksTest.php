<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use Livewire\Livewire;

/**
 * A plan asks and shows only the parts it has.
 *
 * A student who chose review only was asked which way to memorise as well,
 * and the plan was built on that answer while the review direction he chose
 * was half ignored. His plan page then carried two memorising columns that
 * could only ever stay empty.
 */
beforeEach(function () {
    $this->student = Student::factory()->create([
        'circle_id' => Circle::factory()->create(['stage_id' => Stage::factory()->create()->id])->id,
        'status' => 'active',
        'is_data_completed' => true,
    ]);

    $this->actingAs($this->student, 'student');
});

it('builds a review-only plan on the one direction it asks, the review\'s', function () {
    Livewire::test('shared.plan-creator')
        ->set('planType', 'review')
        ->set('reviewDirection', 'forward')
        ->assertSet('fillDirection', 'forward')
        ->set('reviewDirection', 'reverse')
        ->assertSet('fillDirection', 'reverse');
});

it('takes the review direction already chosen when a plan turns review-only', function () {
    Livewire::test('shared.plan-creator')
        ->set('planType', 'hifz_review')
        ->set('fillDirection', 'forward')
        ->set('reviewDirection', 'reverse')
        ->set('planType', 'review')
        ->assertSet('fillDirection', 'reverse');
});

it('leaves a memorising plan\'s own direction alone when its review turns', function () {
    Livewire::test('shared.plan-creator')
        ->set('planType', 'hifz_review')
        ->set('fillDirection', 'forward')
        ->set('reviewDirection', 'reverse')
        ->assertSet('fillDirection', 'forward');
});

it('shows a plan only the columns of the parts it has', function (string $type, array $shown, array $hidden) {
    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'start_date' => now()->toDateString(),
        'days_count' => 1,
        'active_days' => ['Sunday'],
        'plan_type' => $type,
        'status' => 'active',
        'is_approved' => true,
        'created_by_role' => 'student',
        'direction' => 'reverse',
        'review_direction' => 'reverse',
    ]);

    $page = $this->get(route('student.show-plan', $plan->id))->assertSuccessful();

    foreach ($shown as $column) {
        $page->assertSee($column);
    }
    foreach ($hidden as $column) {
        $page->assertDontSee($column);
    }
})->with([
    'review only' => ['review', ['مقرر المراجعة', 'تقييم المراجعة'], ['مقرر الحفظ', 'تقييم الحفظ']],
    'memorising only' => ['hifz', ['مقرر الحفظ', 'تقييم الحفظ'], ['مقرر المراجعة', 'تقييم المراجعة']],
    'both' => ['hifz_review', ['مقرر الحفظ', 'مقرر المراجعة'], []],
]);
