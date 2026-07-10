<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links the sidebar logo to the student dashboard, not the public home page', function () {
    $stage = Stage::factory()->create();
    $circle = Circle::factory()->create(['stage_id' => $stage->id]);
    $student = Student::factory()->create(['circle_id' => $circle->id]);

    $this->actingAs($student, 'student');

    $response = $this->get(route('student.dashboard'));
    $response->assertSuccessful();

    $response->assertSee('href="'.route('dashboard').'"', false);
    expect($response->getContent())->not->toContain('href="'.route('home').'"');
});

it('links the sidebar logo to the teacher dashboard, not the public home page', function () {
    $teacher = Teacher::factory()->create();

    $this->actingAs($teacher, 'teacher');

    $response = $this->get(route('teacher.dashboard'));
    $response->assertSuccessful();

    $response->assertSee('href="'.route('dashboard').'"', false);
    expect($response->getContent())->not->toContain('href="'.route('home').'"');
});
