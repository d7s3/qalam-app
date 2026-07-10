<?php

use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;

it('renders the manager dashboard and messages page with the new sidebar link', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.dashboard'))->assertSuccessful()->assertSee('الرسائل');
    $this->get(route('manager.messages'))->assertSuccessful();
});

it('renders the teacher dashboard and messages page with the new sidebar link', function () {
    $teacher = Teacher::factory()->create();
    $this->actingAs($teacher, 'teacher');

    $this->get(route('teacher.dashboard'))->assertSuccessful()->assertSee('الرسائل');
    $this->get(route('teacher.messages'))->assertSuccessful();
});

it('renders the supervisor dashboard and messages page with the new sidebar link', function () {
    $supervisor = Supervisor::factory()->create();
    $this->actingAs($supervisor, 'supervisor');

    $this->get(route('supervisor.dashboard'))->assertSuccessful()->assertSee('الرسائل');
    $this->get(route('supervisor.messages'))->assertSuccessful();
});

it('renders the guardian dashboard and messages page with the new sidebar link', function () {
    $guardian = Guardian::factory()->create(['is_approved' => true]);
    $this->actingAs($guardian, 'guardian');

    $this->get(route('guardian.dashboard'))->assertSuccessful()->assertSee('الرسائل');
    $this->get(route('guardian.messages'))->assertSuccessful();
});

it('renders the student dashboard and messages page with the new sidebar link', function () {
    $stage = Stage::factory()->create();
    $student = Student::factory()->create();
    $this->actingAs($student, 'student');

    $this->get(route('student.dashboard'))->assertSuccessful()->assertSee('الرسائل');
    $this->get(route('student.messages'))->assertSuccessful();
});
