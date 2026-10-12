<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use Livewire\Livewire;

/**
 * A teacher's home page opens on his day: whom he has marked today out of how
 * many, his students, and his cohort's week of memorisation.
 */
beforeEach(function () {
    $this->cohort = Circle::factory()->create(['stage_id' => Stage::factory()->create()->id]);
    $this->teacher = Teacher::factory()->create(['name' => 'حمزة باوزير', 'is_approved' => true]);
    $this->teacher->circles()->attach($this->cohort->id);
    $this->students = Student::factory()->count(3)->create(['circle_id' => $this->cohort->id, 'status' => 'active']);

    $this->actingAs($this->teacher, 'teacher');
});

it('greets him, and counts today\'s register against his students', function () {
    Carbon\Carbon::setTestNow('2026-10-07 05:00:00'); // A Wednesday, eight in the morning in Riyadh.
    Attendance::create(['student_id' => $this->students[0]->id, 'circle_id' => $this->cohort->id, 'date' => '2026-10-07', 'status' => 'present']);

    Livewire::test('teacher.dashboard')
        ->assertSee('صباح الخير يا أستاذ حمزة')
        ->assertSee('1 / 3')
        ->assertSee('أكمل التحضير')
        ->assertSee(route('teacher.attendance'), false);
});

it('asks him to start the register when nobody is marked yet', function () {
    Carbon\Carbon::setTestNow('2026-10-07 05:00:00');

    Livewire::test('teacher.dashboard')->assertSee('0 / 3')->assertSee('ابدأ التحضير الآن');
});

it('says so on a day the academy does not meet', function () {
    Carbon\Carbon::setTestNow('2026-10-09 05:00:00'); // A Friday.

    Livewire::test('teacher.dashboard')->assertSee('ليس يوم دوام')->assertDontSee('ابدأ التحضير الآن');
});
