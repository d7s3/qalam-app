<?php

use App\Models\Ayah;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Surah;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    // Setup teacher, circle, student, plan
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
        'start_date' => now()->format('Y-m-d'),
        'days_count' => 5,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'description' => 'Test Plan',
        'status' => 'active',
        'plan_type' => 'hifz_review',
        'direction' => 'forward',
        'is_approved' => true,
        'created_by_role' => 'teacher',
    ]);

    StudentPlanDay::create([
        'student_plan_id' => $this->plan->id,
        'date' => now()->format('Y-m-d'),
        'day_name' => now()->dayName,
        'from_ayah_id' => 1,
        'to_ayah_id' => 1,
        'review_from_ayah_id' => 1,
        'review_to_ayah_id' => 1,
    ]);
});

test('teacher can download student plan PDF', function () {
    $this->actingAs($this->teacher, 'teacher');

    $response = $this->get(route('teacher.download-plan-pdf', $this->plan->id));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition', 'attachment; filename="plan_'.$this->student->name.'.pdf"');
});

test('unauthorized teacher cannot download student plan PDF', function () {
    $anotherTeacher = Teacher::factory()->create();
    $this->actingAs($anotherTeacher, 'teacher');

    $response = $this->get(route('teacher.download-plan-pdf', $this->plan->id));

    $response->assertStatus(403);
});
