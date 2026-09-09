<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The discipline boards, and the boys they are allowed to name.
 *
 * These two screens read rather than write, which is exactly why they were left
 * without a test: nothing they do can be undone, so nothing about them looks
 * urgent. But they name boys and count their absences, and a board that reaches
 * past its teacher's cohort hands him a record he has no business reading —
 * silently, and with no way for anybody to notice.
 *
 * They ask `Scope` for their cohorts like everything else here. That is one
 * line, and one line is what a refactor changes.
 */
beforeEach(function () {
    $this->here = Stage::factory()->create();
    $this->there = Stage::factory()->create();

    $this->mine = Circle::factory()->create(['stage_id' => $this->here->id]);
    $this->theirs = Circle::factory()->create(['stage_id' => $this->there->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->mine->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->here->id);

    $this->myStudent = Student::factory()->create([
        'name' => 'طالب حلقتي', 'circle_id' => $this->mine->id, 'stage_id' => $this->here->id,
    ]);

    $this->theirStudent = Student::factory()->create([
        'name' => 'طالب الحلقة الأخرى', 'circle_id' => $this->theirs->id, 'stage_id' => $this->there->id,
    ]);

    // Both boys late the same number of times, so neither is hidden by having
    // nothing against him.
    foreach ([[$this->myStudent, $this->mine], [$this->theirStudent, $this->theirs]] as [$student, $circle]) {
        foreach (range(1, 3) as $day) {
            Attendance::create([
                'student_id' => $student->id,
                'circle_id' => $circle->id,
                'date' => now()->subDays($day)->format('Y-m-d'),
                'status' => 'late',
            ]);
        }
    }
});

it('names a teacher only the boys of his own cohort', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.attendance-discipline')
        ->assertSee('طالب حلقتي')
        ->assertDontSee('طالب الحلقة الأخرى');
});

it('names the Quranic board only his own too', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.quranic-discipline')
        ->assertDontSee('طالب الحلقة الأخرى');
});

it('widens with the office: a supervisor sees his programme and not the next', function () {
    $second = Circle::factory()->create(['stage_id' => $this->here->id]);
    Student::factory()->create([
        'name' => 'طالب حلقة ثانية', 'circle_id' => $second->id, 'stage_id' => $this->here->id,
    ]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('teacher.attendance-discipline')
        ->assertSee('طالب حلقتي')
        // A cohort of his programme he does not teach is still his to see.
        ->assertSee('طالب حلقة ثانية')
        ->assertDontSee('طالب الحلقة الأخرى');
});
