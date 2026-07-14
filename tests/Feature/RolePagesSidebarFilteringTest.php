<?php

use App\Models\Guardian;
use App\Models\RoleScreenPermission;
use App\Models\Screen;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;

function disableScreenForSidebarTest(string $routeName): void
{
    $screen = Screen::where('route_name', $routeName)->firstOrFail();
    RoleScreenPermission::where('screen_id', $screen->id)->delete();
}

it('hides a disabled page from the teacher sidebar but keeps others visible', function () {
    $teacher = Teacher::factory()->create();
    disableScreenForSidebarTest('teacher.discipline');

    $this->actingAs($teacher, 'teacher')
        ->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('الانضباط الحضوري')
        ->assertSee('سجل الحضور');
});

it('hides a disabled page from the supervisor sidebar', function () {
    $supervisor = Supervisor::factory()->create();
    disableScreenForSidebarTest('supervisor.forms');

    $this->actingAs($supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('إدارة النماذج')
        ->assertSee('الحلقات');
});

it('hides a disabled page from the guardian sidebar', function () {
    $guardian = Guardian::factory()->create(['is_approved' => true]);
    disableScreenForSidebarTest('guardian.challenges');

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('المكافآت التحفيزية');
});

it('hides a disabled page from the student sidebar', function () {
    $student = Student::factory()->create();
    disableScreenForSidebarTest('student.reports');

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('التقارير')
        ->assertSee('الحفظ');
});
