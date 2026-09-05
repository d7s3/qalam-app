<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\OccurrenceAttendance;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentSelfProgramEntry;
use App\Services\EducationalLossService;
use App\Support\SelfProgramTrack;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Two losses wear one name. One is a meeting he did not come to; the other is
 * work assigned to a day and left undone. They are answered from different
 * places and are not made good by the same thing.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create(['name' => 'برنامج الحفظ']);
    $this->other = Stage::factory()->create(['name' => 'برنامج آخر']);

    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->student = Student::factory()->create([
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
    ]);

    // A lesson on two days of one week.
    $this->lesson = AcademicCalendarEvent::create([
        'event_name' => 'درس التفسير',
        'start_date' => '2026-09-06',
        'end_date' => '2026-09-10',
        'weekdays' => [1, 3],
        'stage_ids' => [$this->programme->id],
        'is_attendance_period' => false,
    ]);
});

it('expands an event into the days it occupies', function () {
    // 6 September 2026 is a Sunday, 8 September a Tuesday.
    expect($this->lesson->datesBetween('2026-09-06', '2026-09-10'))
        ->toBe(['2026-09-06', '2026-09-08']);
});

it('honours a day taken out and a day added by hand', function () {
    $this->lesson->update([
        'excluded_dates' => ['2026-09-06'],
        'extra_dates' => ['2026-09-09'],
    ]);

    expect($this->lesson->fresh()->datesBetween('2026-09-06', '2026-09-10'))
        ->toBe(['2026-09-08', '2026-09-09']);
});

it('counts a lesson nobody answered for as missed', function () {
    $losses = EducationalLossService::formative($this->student, 'student', '2026-09-06', '2026-09-10');

    expect($losses)->toHaveCount(2);
    expect($losses[0]['event']->event_name)->toBe('درس التفسير');

    // Nothing written is still a miss — it is a miss nobody has explained.
    expect($losses[0]['status'])->toBe('unrecorded');
});

it('drops the day he attended, however late', function () {
    OccurrenceAttendance::create([
        'academic_calendar_event_id' => $this->lesson->id,
        'date' => '2026-09-06',
        'user_id' => $this->student->id,
        'role' => 'student',
        'status' => OccurrenceAttendance::LATE,
    ]);

    $losses = EducationalLossService::formative($this->student, 'student', '2026-09-06', '2026-09-10');

    expect($losses)->toHaveCount(1);
    expect($losses[0]['date'])->toBe('2026-09-08');
});

it('keeps an excused absence as a loss, but says so', function () {
    // He is not blamed for it, and he missed it all the same — the content has
    // to reach him either way.
    OccurrenceAttendance::create([
        'academic_calendar_event_id' => $this->lesson->id,
        'date' => '2026-09-06',
        'user_id' => $this->student->id,
        'role' => 'student',
        'status' => OccurrenceAttendance::EXCUSED,
    ]);

    $losses = EducationalLossService::formative($this->student, 'student', '2026-09-06', '2026-09-10');

    expect($losses)->toHaveCount(2);
    expect($losses[0]['status'])->toBe('excused');
});

it('leaves another programme lesson out of his losses', function () {
    $this->lesson->update(['stage_ids' => [$this->other->id]]);

    expect(EducationalLossService::formative($this->student, 'student', '2026-09-06', '2026-09-10'))
        ->toBe([]);
});

it('treats an attendance period as the frame, not an appointment', function () {
    // Periods say which days are working days. They are what the lessons sit
    // inside, and being inside one is not something to attend.
    $this->lesson->update(['is_attendance_period' => true]);

    expect(EducationalLossService::formative($this->student, 'student', '2026-09-06', '2026-09-10'))
        ->toBe([]);
});

it('reads a self programme shortfall as work left, not a day missed', function () {
    $week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohort->id,
        'program_type' => 'self',
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-10',
    ]);

    $item = SelfProgramItem::create([
        'self_program_week_id' => $week->id,
        'track' => SelfProgramTrack::Maqrou,
        'target_amount' => 20,
        'unit' => 'صفحة',
    ]);

    StudentSelfProgramEntry::create([
        'student_id' => $this->student->id,
        'self_program_item_id' => $item->id,
        'entry_date' => '2026-09-07',
        'amount_done' => 14,
    ]);

    $losses = EducationalLossService::scientific($this->student, '2026-09-06', '2026-09-10');

    $shortfall = collect($losses)->firstWhere('kind', 'self-program');

    expect($shortfall)->not->toBeNull();
    expect($shortfall['expected'])->toContain('20');
    expect($shortfall['done'])->toContain('14');
});

it('drops a track once it is met in full', function () {
    $week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohort->id,
        'program_type' => 'self',
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-10',
    ]);

    $item = SelfProgramItem::create([
        'self_program_week_id' => $week->id,
        'track' => SelfProgramTrack::Maqrou,
        'target_amount' => 20,
        'unit' => 'صفحة',
    ]);

    StudentSelfProgramEntry::create([
        'student_id' => $this->student->id,
        'self_program_item_id' => $item->id,
        'entry_date' => '2026-09-07',
        'amount_done' => 20,
    ]);

    expect(collect(EducationalLossService::scientific($this->student, '2026-09-06', '2026-09-10'))
        ->firstWhere('kind', 'self-program'))->toBeNull();
});
