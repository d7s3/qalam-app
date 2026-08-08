<?php

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;

it('gives the manager sidebar the dark sidebar', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $response = $this->get(route('manager.dashboard'));
    $response->assertSuccessful();
    $response->assertSee('bg-gradient-to-t from-black via-zinc-900 to-zinc-800', false);
});

it('gives the student sidebar the same dark sidebar', function () {
    $stage = Stage::factory()->create();
    $circle = Circle::factory()->create(['stage_id' => $stage->id]);
    $student = Student::factory()->create(['circle_id' => $circle->id]);
    $this->actingAs($student, 'student');

    $response = $this->get(route('student.dashboard'));
    $response->assertSuccessful();
    $response->assertSee('bg-gradient-to-t from-black via-zinc-900 to-zinc-800', false);
});

it('gives the teacher sidebar the same dark sidebar', function () {
    $teacher = Teacher::factory()->create();
    $this->actingAs($teacher, 'teacher');

    $response = $this->get(route('teacher.dashboard'));
    $response->assertSuccessful();
    $response->assertSee('bg-gradient-to-t from-black via-zinc-900 to-zinc-800', false);
});

it('gives the supervisor sidebar the same dark sidebar', function () {
    $supervisor = Supervisor::factory()->create();
    $this->actingAs($supervisor, 'supervisor');

    $response = $this->get(route('supervisor.dashboard'));
    $response->assertSuccessful();
    $response->assertSee('bg-gradient-to-t from-black via-zinc-900 to-zinc-800', false);
});

it('gives the guardian sidebar the same dark sidebar', function () {
    $guardian = Guardian::factory()->create(['is_approved' => true]);
    $this->actingAs($guardian, 'guardian');

    $response = $this->get(route('guardian.dashboard'));
    $response->assertSuccessful();
    $response->assertSee('bg-gradient-to-t from-black via-zinc-900 to-zinc-800', false);
});
