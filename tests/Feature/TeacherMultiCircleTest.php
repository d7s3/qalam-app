<?php

use App\Livewire\Teacher\LeaderboardGrade;
use App\Livewire\Teacher\Leaderboards;
use App\Models\Circle;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TurnReservation;
use App\Models\TurnReservationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-06-10 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circleA = Circle::factory()->create(['stage_id' => $this->stage->id, 'name' => 'حلقة أ']);
    $this->circleB = Circle::factory()->create(['stage_id' => $this->stage->id, 'name' => 'حلقة ب']);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach([$this->circleA->id, $this->circleB->id]);

    $this->studentA = Student::factory()->create([
        'name' => 'طالب الحلقة الأولى',
        'circle_id' => $this->circleA->id,
        'status' => 'active',
        'is_approved' => true,
    ]);
    $this->studentB = Student::factory()->create([
        'name' => 'طالب الحلقة الثانية',
        'circle_id' => $this->circleB->id,
        'status' => 'active',
        'is_approved' => true,
    ]);
});

it('shows students from all teacher circles on the leaderboard grading page', function () {
    $leaderboard = Leaderboard::create([
        'circle_id' => $this->circleA->id,
        'title' => 'مسابقة الحلقتين',
        'competition_type' => 'leaderboard',
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(10),
        'is_active' => true,
        'is_active_for_grading' => true,
        'settings' => [],
    ]);
    $leaderboard->circles()->attach([$this->circleA->id, $this->circleB->id]);

    $this->actingAs($this->teacher, 'teacher');

    $component = Livewire::test(LeaderboardGrade::class, ['leaderboardId' => $leaderboard->id]);

    $shownIds = collect($component->viewData('students'))->pluck('id');

    expect($shownIds->all())->toContain($this->studentA->id);
    expect($shownIds->all())->toContain($this->studentB->id);
});

it('lists competitions from every teacher circle on the leaderboards page', function () {
    $boardA = Leaderboard::create([
        'circle_id' => $this->circleA->id,
        'title' => 'مسابقة الحلقة الأولى',
        'competition_type' => 'leaderboard',
        'start_date' => now()->subDay(),
        'is_active' => true,
        'settings' => [],
    ]);
    $boardB = Leaderboard::create([
        'circle_id' => $this->circleB->id,
        'title' => 'مسابقة الحلقة الثانية',
        'competition_type' => 'leaderboard',
        'start_date' => now()->subDay(),
        'is_active' => true,
        'settings' => [],
    ]);

    $this->actingAs($this->teacher, 'teacher');

    $component = Livewire::test(Leaderboards::class);

    $listedIds = collect($component->get('leaderboards'))->pluck('id');

    expect($listedIds->all())->toContain($boardA->id);
    expect($listedIds->all())->toContain($boardB->id);
});

it('numbers tasmeeh turn reservations sequentially across all teacher circles', function () {
    $now = Carbon\Carbon::now('Asia/Riyadh');
    $session = TurnReservationSession::create([
        'teacher_id' => $this->teacher->id,
        'start_date' => $now->copy()->subDay()->format('Y-m-d'),
        'end_date' => $now->copy()->addDay()->format('Y-m-d'),
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => $now->copy()->subHour()->format('H:i:s'),
        'end_time' => $now->copy()->addHour()->format('H:i:s'),
    ]);

    // A student from each circle reserves; numbering continues, never restarts.
    $this->actingAs($this->studentA, 'student');
    Livewire::test('student.⚡dashboard')->call('reserveTurn', $session->id);

    $this->actingAs($this->studentB, 'student');
    Livewire::test('student.⚡dashboard')->call('reserveTurn', $session->id);

    $turnA = TurnReservation::where('student_id', $this->studentA->id)->first();
    $turnB = TurnReservation::where('student_id', $this->studentB->id)->first();

    expect($turnA->turn_number)->toBe(1);
    expect($turnB->turn_number)->toBe(2);
    expect($turnA->turn_reservation_session_id)->toBe($session->id);
    expect($turnB->turn_reservation_session_id)->toBe($session->id);
});

it('shows the reservation queue to co-teachers of the same circle', function () {
    $coTeacher = Teacher::factory()->create();
    $coTeacher->circles()->attach($this->circleA->id);

    $now = Carbon\Carbon::now('Asia/Riyadh');
    $session = TurnReservationSession::create([
        'teacher_id' => $this->teacher->id,
        'start_date' => $now->copy()->subDay()->format('Y-m-d'),
        'end_date' => $now->copy()->addDay()->format('Y-m-d'),
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => $now->copy()->subHour()->format('H:i:s'),
        'end_time' => $now->copy()->addHour()->format('H:i:s'),
    ]);

    TurnReservation::create([
        'turn_reservation_session_id' => $session->id,
        'student_id' => $this->studentA->id,
        'date' => $now->format('Y-m-d'),
        'turn_number' => 1,
    ]);

    // The co-teacher (who did not create the session) sees the same active queue.
    $this->actingAs($coTeacher, 'teacher');
    $component = Livewire::test('teacher.⚡tasmeeh-manager');

    expect($component->viewData('activeSession'))->not->toBeNull();
    expect($component->viewData('activeSession')->id)->toBe($session->id);
});

it('lets a co-teacher edit the shared session instead of creating a duplicate queue', function () {
    $coTeacher = Teacher::factory()->create();
    $coTeacher->circles()->attach($this->circleA->id);

    $now = Carbon\Carbon::now('Asia/Riyadh');
    $session = TurnReservationSession::create([
        'teacher_id' => $this->teacher->id,
        'start_date' => $now->copy()->subDay()->format('Y-m-d'),
        'end_date' => $now->copy()->addDay()->format('Y-m-d'),
        'days_of_week' => [0, 1, 2, 3, 4],
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
    ]);

    $this->actingAs($coTeacher, 'teacher');

    Livewire::test('teacher.⚡tasmeeh-manager')
        ->call('openSessionModal')
        ->assertSet('sessionStartTime', '16:00')
        ->set('sessionStartTime', '15:00')
        ->set('sessionEndTime', '19:00')
        ->call('saveSession');

    expect(TurnReservationSession::count())->toBe(1);
    expect(Carbon\Carbon::parse($session->refresh()->start_time)->format('H:i'))->toBe('15:00');
});

it('finds the reservation session for students of the teacher second circle', function () {
    $now = Carbon\Carbon::now('Asia/Riyadh');
    TurnReservationSession::create([
        'teacher_id' => $this->teacher->id,
        'start_date' => $now->copy()->subDay()->format('Y-m-d'),
        'end_date' => $now->copy()->addDay()->format('Y-m-d'),
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_time' => $now->copy()->subHour()->format('H:i:s'),
        'end_time' => $now->copy()->addHour()->format('H:i:s'),
    ]);

    $this->actingAs($this->studentB, 'student');

    $component = Livewire::test('student.⚡dashboard');

    expect($component->viewData('activeSession'))->not->toBeNull();
});
