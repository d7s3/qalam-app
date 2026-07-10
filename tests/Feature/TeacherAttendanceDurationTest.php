<?php

use App\Livewire\Teacher\Attendance;
use App\Models\Attendance as AttendanceModel;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    $this->actingAs($this->teacher, 'teacher');
});

it('saves the session duration when marking a student present', function () {
    Livewire::test(Attendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->set('sessionDurationMinutes', 60)
        ->call('markStatus', $this->student->id, 'present');

    $attendance = AttendanceModel::where('student_id', $this->student->id)->first();

    expect($attendance->duration_minutes)->toBe(60);
});

it('saves the session duration for markAllPresent', function () {
    Livewire::test(Attendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->set('sessionDurationMinutes', 45)
        ->call('markAllPresent');

    $attendance = AttendanceModel::where('student_id', $this->student->id)->first();

    expect($attendance->duration_minutes)->toBe(45);

    expect($this->student->totalStudyHours())->toBe(0.8); // round(45/60, 1)
});

it('updates the duration on existing records for the day when changed afterward', function () {
    Livewire::test(Attendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->call('markStatus', $this->student->id, 'present')
        ->set('sessionDurationMinutes', 90);

    $attendance = AttendanceModel::where('student_id', $this->student->id)->first();

    expect($attendance->duration_minutes)->toBe(90);
});

it('preloads the existing session duration when reopening the same circle and date', function () {
    AttendanceModel::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $this->circle->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
        'duration_minutes' => 75,
    ]);

    Livewire::test(Attendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->assertSet('sessionDurationMinutes', 75);
});
