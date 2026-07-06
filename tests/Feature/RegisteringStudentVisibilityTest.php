<?php

use App\Livewire\Manager\Dashboard;
use App\Livewire\Manager\StudentAttendanceList;
use App\Livewire\Teacher\Attendance as TeacherAttendance;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-06-10 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->activeStudent = Student::factory()->create([
        'name' => 'طالب مشارك',
        'circle_id' => $this->circle->id,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $this->registeringStudent = Student::factory()->create([
        'name' => 'طالب تحت التسجيل',
        'circle_id' => $this->circle->id,
        'status' => 'registering',
        'is_approved' => true,
    ]);
    $this->registeringStudent->statusHistories()->create([
        'status' => 'registering',
        'start_date' => '2026-06-01',
    ]);
});

it('hides registering students from the teacher tasmeeh page', function () {
    $this->actingAs($this->teacher, 'teacher');

    $component = Livewire::test('teacher.⚡tasmeeh-manager');

    $shownIds = collect($component->viewData('studentsWithPlansPresent'))
        ->merge($component->viewData('studentsWithPlansAbsent'))
        ->merge($component->viewData('studentsWithoutPlans'))
        ->pluck('id');

    expect($shownIds->all())->toContain($this->activeStudent->id);
    expect($shownIds->all())->not->toContain($this->registeringStudent->id);
});

it('hides registering students from the teacher attendance page', function () {
    $this->actingAs($this->teacher, 'teacher');

    $component = Livewire::test(TeacherAttendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->set('date', '2026-06-10');

    $shownIds = collect($component->get('students'))->pluck('id');

    expect($shownIds->all())->toContain($this->activeStudent->id);
    expect($shownIds->all())->not->toContain($this->registeringStudent->id);
});

it('shows the student on the attendance page only from the date they became active', function () {
    $this->registeringStudent->update(['status' => 'active']);
    $this->registeringStudent->statusHistories()->create([
        'status' => 'active',
        'start_date' => '2026-06-08',
    ]);

    $this->actingAs($this->teacher, 'teacher');

    // On a date within the registering period they stay hidden.
    $before = Livewire::test(TeacherAttendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->set('date', '2026-06-05');
    expect(collect($before->get('students'))->pluck('id')->all())
        ->not->toContain($this->registeringStudent->id);

    // From the activation date onward they appear.
    $after = Livewire::test(TeacherAttendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->set('date', '2026-06-09');
    expect(collect($after->get('students'))->pluck('id')->all())
        ->toContain($this->registeringStudent->id);
});

it('hides registering students from the manager circle attendance list', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $component = Livewire::test(StudentAttendanceList::class, [
        'circleId' => $this->circle->id,
        'date' => '2026-06-10',
    ]);

    $shownIds = collect($component->get('students'))->pluck('id');

    expect($shownIds->all())->toContain($this->activeStudent->id);
    expect($shownIds->all())->not->toContain($this->registeringStudent->id);
});

it('excludes attendance taken during the registering period from manager dashboard counts', function () {
    foreach ([$this->activeStudent, $this->registeringStudent] as $student) {
        Attendance::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'circle_id' => $this->circle->id,
            'date' => '2026-06-10',
            'status' => 'present',
        ]);
    }

    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $component = Livewire::test(Dashboard::class)
        ->set('attendancePeriod', 'today');

    $data = $component->instance()->getAttendanceDataProperty();

    // Only the active student counts: one present record and one expected student.
    expect($data['present'])->toBe(1);
    expect($data['totalStudents'])->toBe(1);
});

it('counts the student in dashboard reports again once they become active', function () {
    // The student was registering until 2026-06-08, then became active.
    $this->registeringStudent->update(['status' => 'active']);
    $this->registeringStudent->statusHistories()->create([
        'status' => 'active',
        'start_date' => '2026-06-08',
    ]);

    // A record from the registering period and one after activation.
    foreach (['2026-06-05', '2026-06-10'] as $date) {
        Attendance::create([
            'student_id' => $this->registeringStudent->id,
            'teacher_id' => $this->teacher->id,
            'circle_id' => $this->circle->id,
            'date' => $date,
            'status' => 'present',
        ]);
    }

    $included = Attendance::query()
        ->whereRaw(Attendance::registeringExclusionSql())
        ->pluck('date')
        ->map(fn ($d) => Carbon\Carbon::parse($d)->format('Y-m-d'));

    expect($included->all())->toContain('2026-06-10');
    expect($included->all())->not->toContain('2026-06-05');
});
