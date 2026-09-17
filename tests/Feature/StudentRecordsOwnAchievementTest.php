<?php

use App\Models\AppNotification;
use App\Models\Ayah;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Surah;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The student writing his own achievement.
 *
 * The grade was the teacher's to write and nobody else's, so a boy who recited
 * at home on a Thursday had no way to say so: the day sat at «قيد الانتظار»
 * until somebody remembered it, and the week's points with it.
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-09-15 18:00:00');

    $programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $programme->id]);
    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);

    $this->student = Student::factory()->create([
        'circle_id' => $this->cohort->id,
        'stage_id' => $programme->id,
        'is_approved' => true,
    ]);

    $this->plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => '2026-09-13',
        'days_count' => 5,
        'active_days' => json_encode([0, 1, 2, 3, 4]),
        'plan_type' => 'hifz',
        'status' => 'active',
        'is_approved' => true,
    ]);

    $surah = Surah::create([
        'number' => 1, 'name_arabic' => 'الفاتحة', 'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah', 'revelation_order' => 5, 'verses_count' => 7,
        'start_page' => 1, 'end_page' => 1,
    ]);

    $ayah = fn (int $n) => Ayah::create([
        'surah_id' => $surah->id, 'verse_number' => $n, 'verse_key' => "1:{$n}",
        'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1,
        'page_number' => 1, 'ruku_number' => 1, 'manzil_number' => 1,
        'text_uthmani' => '…',
    ]);

    $this->from = $ayah(1);
    $this->to = $ayah(7);

    $this->day = StudentPlanDay::create([
        'student_plan_id' => $this->plan->id,
        'date' => '2026-09-15',
        'day_name' => 'الثلاثاء',
        'from_ayah_id' => $this->from->id,
        'to_ayah_id' => $this->to->id,
    ]);
});

function studentPlanScreen($student, int $planId)
{
    return Livewire::actingAs($student, 'student')
        ->test('student.show-plan', ['planId' => $planId]);
}

it('lets the student write today’s grade himself, and it counts at once', function () {
    studentPlanScreen($this->student, $this->plan->id)
        ->call('record', $this->day->id, 'hifz', 3);

    $day = $this->day->fresh();

    expect($day->hifz_achievement)->toBe(3);
    expect($day->hifz_graded_at)->not->toBeNull();
});

it('tells the cohort’s teachers that the boy wrote it himself', function () {
    studentPlanScreen($this->student, $this->plan->id)
        ->call('record', $this->day->id, 'hifz', 2);

    $notice = AppNotification::where('recipient_type', 'teacher')
        ->where('recipient_id', $this->teacher->id)
        ->first();

    expect($notice)->not->toBeNull();
    expect($notice->body)->toContain($this->student->name);
});

it('refuses to overwrite a grade that is already there', function () {
    // Once a grade exists it is the teacher's to change, so a boy cannot
    // quietly raise one he was given.
    $this->day->update(['hifz_achievement' => 1]);

    studentPlanScreen($this->student, $this->plan->id)
        ->call('record', $this->day->id, 'hifz', 3)
        ->assertForbidden();

    expect($this->day->fresh()->hifz_achievement)->toBe(1);
});

it('refuses a day that has not happened yet', function () {
    $tomorrow = StudentPlanDay::create([
        'student_plan_id' => $this->plan->id,
        'date' => '2026-09-16',
        'day_name' => 'الأربعاء',
        'from_ayah_id' => $this->from->id,
        'to_ayah_id' => $this->to->id,
    ]);

    studentPlanScreen($this->student, $this->plan->id)
        ->call('record', $tomorrow->id, 'hifz', 3)
        ->assertForbidden();

    expect($tomorrow->fresh()->hifz_achievement)->toBeNull();
});

it('lets him fill in a day that has already passed', function () {
    $past = StudentPlanDay::create([
        'student_plan_id' => $this->plan->id,
        'date' => '2026-09-13',
        'day_name' => 'الأحد',
    ]);

    studentPlanScreen($this->student, $this->plan->id)
        ->call('record', $past->id, 'hifz', 2);

    expect($past->fresh()->hifz_achievement)->toBe(2);
});

it('will not even open another student’s plan', function () {
    $other = Student::factory()->create(['circle_id' => $this->cohort->id, 'is_approved' => true]);

    Livewire::actingAs($other, 'student')->test('student.show-plan', ['planId' => $this->plan->id]);
})->throws(ModelNotFoundException::class);

it('refuses a day out of another student’s plan', function () {
    // The screen is his own; the day sent to it is not. Ownership is settled
    // from the day, not from the plan the screen happens to be showing.
    $other = Student::factory()->create(['circle_id' => $this->cohort->id, 'is_approved' => true]);

    $theirPlan = StudentPlan::create([
        'student_id' => $other->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => '2026-09-13',
        'days_count' => 5,
        'active_days' => json_encode([0, 1, 2, 3, 4]),
        'plan_type' => 'hifz',
        'status' => 'active',
        'is_approved' => true,
    ]);

    $theirDay = StudentPlanDay::create([
        'student_plan_id' => $theirPlan->id,
        'date' => '2026-09-15',
        'day_name' => 'الثلاثاء',
    ]);

    expect(fn () => studentPlanScreen($this->student, $this->plan->id)
        ->call('record', $theirDay->id, 'hifz', 3))
        ->toThrow(ModelNotFoundException::class);

    expect($theirDay->fresh()->hifz_achievement)->toBeNull();
});

it('refuses a grade outside the three it offers', function () {
    studentPlanScreen($this->student, $this->plan->id)
        ->call('record', $this->day->id, 'hifz', 9)
        ->assertStatus(400);

    expect($this->day->fresh()->hifz_achievement)->toBeNull();
});

/**
 * The screen has to offer the buttons before any of the above matters.
 *
 * `date` is cast, so it arrives as a Carbon; compared against a `Y-m-d` string
 * PHP calls every object the greater of the two. Every day therefore read as
 * «قادم», today's included, and the whole feature was invisible while its own
 * tests passed — they called the method rather than pressing the button.
 */
it('offers today the buttons, and tomorrow none', function () {
    StudentPlanDay::create([
        'student_plan_id' => $this->plan->id,
        'date' => '2026-09-16',
        'day_name' => 'الأربعاء',
        'from_ayah_id' => $this->from->id,
        'to_ayah_id' => $this->to->id,
    ]);

    $screen = studentPlanScreen($this->student, $this->plan->id);

    $screen->assertSee('اليوم');
    $screen->assertSee('قادم');

    // Today's row carries the three grades to choose from.
    $screen->assertSeeHtml("record({$this->day->id}, 'hifz', 3)");
});

it('offers nothing on a day already graded', function () {
    $this->day->update(['hifz_achievement' => 3]);

    studentPlanScreen($this->student, $this->plan->id)
        ->assertDontSeeHtml("record({$this->day->id}, 'hifz', 3)");
});
