<?php

use App\Models\Ayah;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Surah;
use App\Models\Teacher;

beforeEach(function () {
    Surah::create([
        'id' => 1, 'number' => 1, 'name_arabic' => 'الفاتحة', 'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah', 'revelation_order' => 1, 'verses_count' => 7,
        'start_page' => 1, 'end_page' => 1,
    ]);

    foreach ([1, 2] as $n) {
        Ayah::create([
            'id' => $n, 'surah_id' => 1, 'verse_number' => $n, 'page_number' => 1,
            'line_number_start' => 1, 'line_number_end' => 1, 'verse_key' => '1:'.$n,
            'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1, 'ruku_number' => 1,
            'manzil_number' => 1, 'text_uthmani' => 'آية '.$n,
        ]);
    }

    $this->teacher = Teacher::factory()->create();
    $this->circle = Circle::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    $this->plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => '2026-07-01',
        'days_count' => 3,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'status' => 'active',
        'plan_type' => 'hifz_review',
        'created_by_role' => 'teacher',
    ]);

    foreach (range(0, 2) as $i) {
        StudentPlanDay::create([
            'student_plan_id' => $this->plan->id,
            'date' => '2026-07-0'.($i + 1),
            'day_name' => 'الأحد',
            'from_ayah_id' => 1,
            'to_ayah_id' => 2,
            'review_from_ayah_id' => 1,
            'review_to_ayah_id' => 2,
            'hifz_achievement' => $i === 0 ? 3 : null,
        ]);
    }
});

it('serves a student plan days as json', function () {
    $this->actingAs($this->teacher, 'teacher');

    $response = $this->getJson(route('teacher.tasmeeh.days', $this->student))
        ->assertOk();

    $response->assertJsonPath('student.id', $this->student->id)
        ->assertJsonCount(1, 'quran_plans')
        ->assertJsonCount(3, 'quran_plans.0.days');

    $day = $response->json('quran_plans.0.days.0');

    expect($day['date'])->toBe('2026-07-01')
        ->and($day['hifz']['achievement'])->toBe(3)
        ->and($day['hifz']['range'])->not->toBeEmpty()
        // The link is built server-side so the browser never reimplements it.
        ->and($day['hifz']['links'][0]['url'])->toBe('https://quran.com/ar/1/1-2')
        ->and($day['hifz']['links'][0]['name'])->toBe('الفاتحة');
});

/**
 * The whole point of the change: the same days used to arrive as megabytes of
 * server-rendered HTML. If this ever creeps back up, the page is slow again.
 */
it('keeps the days payload small enough for a phone', function () {
    $this->actingAs($this->teacher, 'teacher');

    $bytes = strlen($this->get(route('teacher.tasmeeh.days', $this->student))->getContent());

    expect($bytes)->toBeLessThan(20 * 1024);
});

it('refuses a teacher who does not have the student in a circle', function () {
    $this->actingAs(Teacher::factory()->create(), 'teacher');

    $this->getJson(route('teacher.tasmeeh.days', $this->student))->assertForbidden();
});

it('refuses a guest', function () {
    $this->getJson(route('teacher.tasmeeh.days', $this->student))->assertUnauthorized();
});

it('validates what kind of text is being asked for', function () {
    $this->actingAs($this->teacher, 'teacher');

    $this->getJson(route('teacher.tasmeeh.text', $this->student).'?kind=quran&id=1&part=hifz')
        ->assertStatus(422);
});
