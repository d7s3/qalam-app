<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-06-10 10:00:00'); // Wednesday

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);
});

function markAttendance(Student $student, Teacher $teacher, Circle $circle, string $date, string $status = 'present'): Attendance
{
    return Attendance::create([
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'circle_id' => $circle->id,
        'date' => $date,
        'status' => $status,
    ]);
}

it('returns 0 streak when there is no attendance', function () {
    expect($this->student->currentAttendanceStreakDays())->toBe(0);
});

it('counts a consecutive streak ending today', function () {
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-08');
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-09');
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-10');

    expect($this->student->currentAttendanceStreakDays())->toBe(3);
});

it('still counts the streak when today has no session yet but yesterday does', function () {
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-08');
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-09');

    expect($this->student->currentAttendanceStreakDays())->toBe(2);
});

it('breaks the streak on a gap', function () {
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-05');
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-09');
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-10');

    expect($this->student->currentAttendanceStreakDays())->toBe(2);
});

it('returns 0 streak when the last activity was more than a day ago', function () {
    markAttendance($this->student, $this->teacher, $this->circle, '2026-06-05');

    expect($this->student->currentAttendanceStreakDays())->toBe(0);
});
