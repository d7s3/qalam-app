<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\OccurrenceAttendance;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Task;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The screen every office opens on itself: what I am expected at, what is on
 * me, and what I missed while it can still be made good.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->student = Student::factory()->create([
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
    ]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);

    $this->lesson = AcademicCalendarEvent::create([
        'event_name' => 'درس التفسير',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'stage_ids' => [$this->programme->id],
        'is_attendance_period' => false,
    ]);
});

it('opens for a student', function () {
    $this->actingAs($this->student, 'student')
        ->get(route('student.my-day'))
        ->assertSuccessful()
        ->assertSee('يومي')
        ->assertSee('درس التفسير')
        // The deepest markup, which an inline @php would have cut away.
        ->assertSee('معذور');
});

it('lets him record his own attendance', function () {
    Livewire::actingAs($this->student, 'student')
        ->test('shared.my-day')
        ->set('asRole', 'student')
        ->set('date', '2026-09-07')
        ->call('record', $this->lesson->id, 'present');

    $answer = OccurrenceAttendance::firstOrFail();

    expect($answer->user_id)->toBe($this->student->id);
    expect($answer->status)->toBe('present');
    expect($answer->self_recorded)->toBeTrue();
});

it('refuses an answer for a day the appointment does not fall on', function () {
    Livewire::actingAs($this->student, 'student')
        ->test('shared.my-day')
        ->set('asRole', 'student')
        ->set('date', '2026-10-15')
        ->call('record', $this->lesson->id, 'present')
        ->assertStatus(404);

    expect(OccurrenceAttendance::count())->toBe(0);
});

it('lets a teacher record his own on the same appointment', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('shared.my-day')
        ->set('asRole', 'teacher')
        ->set('date', '2026-09-07')
        ->call('record', $this->lesson->id, 'late');

    expect(OccurrenceAttendance::where('user_id', $this->teacher->id)->value('status'))->toBe('late');
});

it('finishes a task from the day it is on', function () {
    $task = Task::create([
        'title' => 'تسميع المراجعة',
        'due_date' => '2026-09-07',
        'status' => 'pending',
        'assigned_to_id' => $this->student->id,
        'assigned_to_type' => Student::class,
        'created_by_id' => $this->teacher->id,
        'created_by_type' => Teacher::class,
    ]);

    Livewire::actingAs($this->student, 'student')
        ->test('shared.my-day')
        ->set('asRole', 'student')
        ->set('date', '2026-09-07')
        ->call('finish', $task->id);

    expect($task->fresh()->isDone())->toBeTrue();
    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('refuses to finish a task assigned to somebody else', function () {
    $other = Student::factory()->create(['circle_id' => $this->cohort->id]);

    $task = Task::create([
        'title' => 'ليست مهمته',
        'due_date' => '2026-09-07',
        'status' => 'pending',
        'assigned_to_id' => $other->id,
        'assigned_to_type' => Student::class,
        'created_by_id' => $this->teacher->id,
        'created_by_type' => Teacher::class,
    ]);

    expect(fn () => Livewire::actingAs($this->student, 'student')
        ->test('shared.my-day')
        ->set('asRole', 'student')
        ->call('finish', $task->id))
        ->toThrow(ModelNotFoundException::class);

    expect($task->fresh()->isDone())->toBeFalse();
});
