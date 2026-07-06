<?php

use App\Livewire\Manager\Students as ManagerStudents;
use App\Livewire\Teacher\Attendance as TeacherAttendance;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\StudentStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-06-10 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create([
        'name' => 'طالب الحالة',
        'circle_id' => $this->circle->id,
        'status' => 'registering',
        'is_approved' => true,
    ]);
    $this->student->statusHistories()->create([
        'status' => 'registering',
        'start_date' => '2026-06-01',
    ]);
});

it('backdates a status change to the chosen effective date', function () {
    StudentStatusService::changeStatus($this->student, 'active', '2026-06-05');

    expect($this->student->refresh()->status)->toBe('active');

    $latest = $this->student->statusHistories()->orderByDesc('start_date')->orderByDesc('id')->first();
    expect($latest->status)->toBe('active');
    expect($latest->start_date->format('Y-m-d'))->toBe('2026-06-05');

    // The previous registering row was closed at the effective date.
    $previous = $this->student->statusHistories()->where('status', 'registering')->first();
    expect($previous->end_date->format('Y-m-d'))->toBe('2026-06-05');
});

it('corrects the effective date when re-saving the same status with a new date', function () {
    StudentStatusService::changeStatus($this->student, 'active', '2026-06-08');
    StudentStatusService::changeStatus($this->student, 'active', '2026-06-03');

    // No duplicate rows: the existing active row moved to the corrected date.
    expect($this->student->statusHistories()->where('status', 'active')->count())->toBe(1);
    expect(
        $this->student->statusHistories()->where('status', 'active')->first()->start_date->format('Y-m-d')
    )->toBe('2026-06-03');
});

it('reflects a backdated activation on the teacher attendance page', function () {
    StudentStatusService::changeStatus($this->student, 'active', '2026-06-05');

    $this->actingAs($this->teacher, 'teacher');

    // Registering on 2026-06-03: hidden. Active from 2026-06-05: visible.
    $before = Livewire::test(TeacherAttendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->set('date', '2026-06-03');
    expect(collect($before->get('students'))->pluck('id')->all())
        ->not->toContain($this->student->id);

    $after = Livewire::test(TeacherAttendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->set('date', '2026-06-06');
    expect(collect($after->get('students'))->pluck('id')->all())
        ->toContain($this->student->id);
});

it('lets the manager change status with an effective date from the students page', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test(ManagerStudents::class)
        ->call('edit', $this->student->id)
        ->set('editStatus', 'active')
        ->set('editStatusDate', '2026-06-04')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->student->refresh()->status)->toBe('active');
    $latest = $this->student->statusHistories()->orderByDesc('start_date')->orderByDesc('id')->first();
    expect($latest->start_date->format('Y-m-d'))->toBe('2026-06-04');
});

it('rejects a future effective date', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test(ManagerStudents::class)
        ->call('edit', $this->student->id)
        ->set('editStatus', 'active')
        ->set('editStatusDate', '2026-06-15')
        ->call('save')
        ->assertHasErrors(['editStatusDate']);

    expect($this->student->refresh()->status)->toBe('registering');
});

it('deletes a wrong history entry and re-syncs the current status', function () {
    StudentStatusService::changeStatus($this->student, 'suspended', '2026-06-07');
    expect($this->student->refresh()->status)->toBe('suspended');

    $wrongEntry = $this->student->statusHistories()->where('status', 'suspended')->first();

    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test(ManagerStudents::class)
        ->call('edit', $this->student->id)
        ->call('deleteStatusHistory', $wrongEntry->id);

    expect($this->student->statusHistories()->count())->toBe(1);
    expect($this->student->refresh()->status)->toBe('registering');
});
