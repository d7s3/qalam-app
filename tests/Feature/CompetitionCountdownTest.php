<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * How many days are left in the competition.
 *
 * Today was read in Riyadh and the end date arrived as a `date` cast at
 * midnight UTC, so the subtraction kept the three hours between them and the
 * student's screen said «متبقي 45.125 يوم».
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-07-07 10:00:00');

    $stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $stage->id]);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    AcademicCalendarEvent::create([
        'event_name' => 'دوام كامل',
        'start_date' => now()->subDays(30)->format('Y-m-d'),
        'end_date' => now()->addDays(60)->format('Y-m-d'),
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5, 6, 7],
        'is_visible' => true,
    ]);

    $this->actingAs($this->student, 'student');
});

function competitionEndingIn(int $days, Circle $circle): Leaderboard
{
    $board = Leaderboard::create([
        'circle_id' => $circle->id,
        'title' => 'مسابقة الفصل الأول',
        'competition_type' => 'points',
        'start_date' => now()->subDays(10),
        'end_date' => now()->addDays($days),
        'is_active' => true,
        'settings' => [],
    ]);
    $board->circles()->attach($circle->id);

    return $board;
}

it('counts the days left as whole days, not as a fraction of one', function () {
    competitionEndingIn(45, $this->circle);

    $html = $this->get(route('student.dashboard'))->assertSuccessful()->getContent();

    expect(str_contains($html, 'متبقي 45 يوم'))->toBeTrue('العدّاد لا يذكر الأيام الباقية كاملةً');
    expect(str_contains($html, 'متبقي 45.'))->toBeFalse('العدّاد يكتب كسراً من اليوم');
});

it('still calls the closing day the last one', function () {
    competitionEndingIn(0, $this->circle);

    $this->get(route('student.dashboard'))->assertSuccessful()->assertSee('اليوم الأخير!');
});

it('still says the competition is over once its day has passed', function () {
    competitionEndingIn(-3, $this->circle);

    $this->get(route('student.dashboard'))->assertSuccessful()->assertSee('انتهت المنافسة');
});
