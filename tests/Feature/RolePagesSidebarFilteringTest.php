<?php

use App\Models\DisabledRolePage;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;

it('hides a disabled page from the teacher sidebar but keeps others visible', function () {
    $teacher = Teacher::factory()->create();
    DisabledRolePage::create(['role' => 'teacher', 'route' => 'teacher.discipline']);

    $this->actingAs($teacher, 'teacher')
        ->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('الانضباط الحضوري')
        ->assertSee('سجل الحضور');
});

it('hides a disabled page from the supervisor sidebar', function () {
    $supervisor = Supervisor::factory()->create();
    DisabledRolePage::create(['role' => 'supervisor', 'route' => 'supervisor.forms']);

    $this->actingAs($supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('إدارة النماذج')
        ->assertSee('الحلقات');
});

it('hides a disabled page from the guardian sidebar', function () {
    $guardian = Guardian::factory()->create(['is_approved' => true]);
    DisabledRolePage::create(['role' => 'guardian', 'route' => 'guardian.challenges']);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('المكافآت التحفيزية');
});

it('hides a disabled page from the student sidebar', function () {
    $student = Student::factory()->create();
    DisabledRolePage::create(['role' => 'student', 'route' => 'student.reports']);

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('التقارير')
        ->assertSee('الحفظ');
});
