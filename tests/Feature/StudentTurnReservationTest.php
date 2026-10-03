<?php

use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TurnReservation;
use App\Models\TurnReservationSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A student books his recitation turn with his own cohort's teacher. The
 * session arrives as an id from the browser, so another teacher's queue must
 * not take him.
 */
function sessionFor(Teacher $teacher): TurnReservationSession
{
    return TurnReservationSession::create([
        'teacher_id' => $teacher->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
    ]);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00', 'Asia/Riyadh'));

    $circle = Circle::factory()->create();
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($circle->id);

    $this->student = Student::factory()->create(['circle_id' => $circle->id, 'status' => 'active', 'is_approved' => true]);
    $this->ownSession = sessionFor($teacher);

    $strangerTeacher = Teacher::factory()->create();
    $strangerTeacher->circles()->attach(Circle::factory()->create()->id);
    $this->otherSession = sessionFor($strangerTeacher);

    $this->actingAs($this->student, 'student');
});

it('books a turn only with his own cohort\'s teacher', function (string $screen) {
    // No competition is running, so the competition screen renders nothing —
    // and must still answer, as it must when one ends with the page open.
    Livewire::test($screen)->call('reserveTurn', $this->otherSession->id);

    expect(TurnReservation::where('turn_reservation_session_id', $this->otherSession->id)->exists())->toBeFalse();

    Livewire::test($screen)->call('reserveTurn', $this->ownSession->id);

    expect(TurnReservation::where('turn_reservation_session_id', $this->ownSession->id)->where('student_id', $this->student->id)->exists())->toBeTrue();
})->with(['student.dashboard', 'student.gamification-dashboard']);

it('offers a student no debugging method to call', function () {
    expect(fn () => Livewire::test('student.gamification-dashboard')->call('testMethod'))
        ->toThrow(MethodNotFoundException::class);
});
