<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Manager;
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

it('opens the manager\'s home on the same head', function () {
    Carbon\Carbon::setTestNow('2026-10-07 05:00:00');

    $this->actingAs(Manager::factory()->create(['name' => 'فيصل بخش']), 'manager')
        ->get(route('manager.dashboard'))
        ->assertSuccessful()
        ->assertSee('صباح الخير')
        ->assertSee('فيصل، هذه الأكاديمية اليوم.');
});

it('marks only «الرئيسية» as where a student is on his home page', function () {
    // Two links to sections of the same page took the URL for their own and
    // lit up beside it.
    $student = Student::factory()->create(['circle_id' => $this->cohort->id, 'is_data_completed' => true]);

    $html = $this->actingAs($student, 'student')->get(route('student.dashboard'))->getContent();

    preg_match_all('/<a href="[^"]*#(?:achievements|leaderboard-standings)"[^>]*>/', $html, $sectionLinks);

    expect(substr_count($html, 'data-current="data-current"'))->toBe(1)
        ->and($sectionLinks[0])->toHaveCount(2)
        ->each->toContain('wire:current.ignore');
});
