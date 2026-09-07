<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Support\Access;
use App\Support\QuranicStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The application was built when every student was a memoriser, so the mushaf,
 * the hifz and the review sat in every student's navigation. The centre has
 * moved: the self programme is what a student has, and the Quranic interface
 * is what a حلقة adds on top.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();

    $this->halaqah = Circle::factory()->create(['stage_id' => $this->programme->id, 'is_quranic' => true]);
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id, 'is_quranic' => false]);

    QuranicStudent::forget();
});

it('reads the circle rather than the student', function () {
    $inHalaqah = Student::factory()->create(['circle_id' => $this->halaqah->id]);
    $inCohort = Student::factory()->create(['circle_id' => $this->cohort->id]);

    expect(QuranicStudent::applies($inHalaqah))->toBeTrue();
    expect(QuranicStudent::applies($inCohort))->toBeFalse();
});

it('counts a student in no circle as not one', function () {
    // Nothing has placed him anywhere, and the self programme is what he has.
    expect(QuranicStudent::applies(Student::factory()->create(['circle_id' => null])))->toBeFalse();
});

it('opens the memorisation pages to the student of a حلقة', function () {
    $student = Student::factory()->create(['circle_id' => $this->halaqah->id]);

    foreach (QuranicStudent::QURANIC_ONLY as $page) {
        expect(Access::canSee($student, 'student', $page))->toBeTrue();
    }
});

it('withholds them from a student of an ordinary cohort', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohort->id]);

    foreach (QuranicStudent::QURANIC_ONLY as $page) {
        expect(Access::canSee($student, 'student', $page))->toBeFalse();
    }
});

it('leaves him everything else he had', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohort->id]);

    foreach ([
        'student.self-program',
        'student.my-day',
        'student.attendance',
        'student.calendar',
        'student.reports',
        // An exam is not necessarily a memorisation exam, so it is not withheld.
        'student.exams',
    ] as $page) {
        expect(Access::canSee($student, 'student', $page))->toBeTrue();
    }
});

it('changes nothing until the academy marks its circles', function () {
    // `is_quranic` was added defaulting to true so nothing already running
    // would change, and the academy's circles are all still Quranic. A student
    // in one of them keeps exactly what he had.
    // Read back rather than trusted: the factory names no value, and the
    // instance in hand does not carry the column's default until it is.
    $circle = Circle::factory()->create(['stage_id' => $this->programme->id])->fresh();
    $student = Student::factory()->create(['circle_id' => $circle->id]);

    expect($circle->is_quranic)->toBeTrue();
    expect(Access::canSee($student, 'student', 'student.plan'))->toBeTrue();
});

it('hides the pages from his navigation too', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohort->id]);

    $html = $this->actingAs($student, 'student')
        ->get(route('student.dashboard'))
        ->assertSuccessful()
        ->getContent();

    // The sidebar asks the same one answer every page asks, so hiding them
    // there follows from the rule rather than being arranged separately.
    expect($html)->not->toContain(route('student.hifz'))
        ->and($html)->not->toContain(route('student.review'))
        ->and($html)->toContain(route('student.self-program'));
});

it('sends him to his own programme where it used to send him to a mushaf plan', function () {
    // The dashboard is still written around the memorisation journey, and its
    // calls to action pointed at a plan a student outside a حلقة has no reason
    // to hold. They point at his programme instead.
    $outside = Student::factory()->create(['circle_id' => $this->cohort->id]);
    $inside = Student::factory()->create(['circle_id' => $this->halaqah->id]);

    $html = $this->actingAs($outside, 'student')->get(route('student.dashboard'))->getContent();
    expect($html)->not->toContain('/student/plan"');

    QuranicStudent::forget();

    $html = $this->actingAs($inside, 'student')->get(route('student.dashboard'))->getContent();
    expect($html)->toContain(route('student.plan'));
});

it('leaves the super administrator above it', function () {
    $admin = Manager::factory()->create(['is_super_admin' => true, 'circle_id' => $this->cohort->id]);

    expect(Access::canSee($admin, 'student', 'student.plan'))->toBeTrue();
});
