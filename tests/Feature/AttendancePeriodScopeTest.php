<?php

use App\Livewire\Teacher\Attendance;
use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\CircleReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-08 10:00:00'); // Wednesday.

    $this->primary = Stage::factory()->create(['name' => 'الابتدائية']);
    $this->middle = Stage::factory()->create(['name' => 'المتوسطة']);
});

/**
 * Build a period and forget the request's cached periods, which the app holds
 * for the length of a request and a test would otherwise carry between saves.
 */
function period(array $attributes): AcademicCalendarEvent
{
    $period = AcademicCalendarEvent::create(array_merge([
        'event_name' => 'فترة دوام الدفعات',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5], // Sunday to Thursday.
        'is_visible' => true,
    ], $attributes));

    AcademicCalendarEvent::forgetPeriodCache();

    return $period;
}

it('falls back to the ordinary week when the calendar holds no period', function () {
    expect(AcademicCalendarEvent::isWorkingDay('2026-07-08'))->toBeTrue()      // Wednesday.
        ->and(AcademicCalendarEvent::isWorkingDay('2026-07-10'))->toBeFalse(); // Friday.
});

it('gives each stage the working days of its own period', function () {
    period(['weekdays' => [1, 2], 'stage_ids' => [$this->primary->id]]);  // Sun, Mon.
    period(['weekdays' => [3, 4], 'stage_ids' => [$this->middle->id]]);   // Tue, Wed.

    // Sunday 2026-07-05 belongs to the primary stage's week only.
    expect(AcademicCalendarEvent::isWorkingDay('2026-07-05', $this->primary->id))->toBeTrue()
        ->and(AcademicCalendarEvent::isWorkingDay('2026-07-05', $this->middle->id))->toBeFalse()
        // Wednesday 2026-07-08 belongs to the middle stage's week only.
        ->and(AcademicCalendarEvent::isWorkingDay('2026-07-08', $this->primary->id))->toBeFalse()
        ->and(AcademicCalendarEvent::isWorkingDay('2026-07-08', $this->middle->id))->toBeTrue();
});

it('lets a period with no stage speak for every stage', function () {
    period(['weekdays' => [1]]); // Sunday, academy-wide.

    expect(AcademicCalendarEvent::isWorkingDay('2026-07-05', $this->primary->id))->toBeTrue()
        ->and(AcademicCalendarEvent::isWorkingDay('2026-07-05', $this->middle->id))->toBeTrue()
        ->and(AcademicCalendarEvent::isWorkingDay('2026-07-06', $this->primary->id))->toBeFalse();
});

it('counts a named extra day even though its weekday is a day off', function () {
    period(['extra_dates' => ['2026-07-11']]); // A Saturday.

    expect(AcademicCalendarEvent::isWorkingDay('2026-07-11'))->toBeTrue()
        ->and(AcademicCalendarEvent::workingDaysBetween('2026-07-09', '2026-07-12'))
        ->toBe(['2026-07-09', '2026-07-11', '2026-07-12']);
});

it('drops a named excluded day even though its weekday is a working day', function () {
    period(['excluded_dates' => ['2026-07-06']]); // A Monday.

    expect(AcademicCalendarEvent::isWorkingDay('2026-07-06'))->toBeFalse()
        ->and(AcademicCalendarEvent::workingDaysBetween('2026-07-05', '2026-07-07'))
        ->toBe(['2026-07-05', '2026-07-07']);
});

it('lets an exclusion win over an extra day named on another period', function () {
    period(['extra_dates' => ['2026-07-11']]);
    period(['excluded_dates' => ['2026-07-11']]);

    expect(AcademicCalendarEvent::isWorkingDay('2026-07-11'))->toBeFalse();
});

/**
 * A plan laid out across the gap between two terms has always been allowed to
 * run straight through it. A closure is the manager naming that exact date, so
 * it stops a plan day wherever it falls.
 */
it('keeps scheduling between terms but never on an excluded day', function () {
    period(['start_date' => '2026-07-01', 'end_date' => '2026-07-15', 'excluded_dates' => ['2026-07-06', '2026-07-22']]);

    expect(AcademicCalendarEvent::isSchedulable('2026-07-22'))->toBeFalse()    // Excluded, outside the term.
        ->and(AcademicCalendarEvent::isSchedulable('2026-07-06'))->toBeFalse() // Excluded, inside it.
        ->and(AcademicCalendarEvent::isSchedulable('2026-07-21'))->toBeTrue()  // Between terms.
        ->and(AcademicCalendarEvent::isSchedulable('2026-07-07'))->toBeTrue(); // An ordinary term day.
});

it('reports the working times of the stage on a date', function () {
    period([
        'stage_ids' => [$this->primary->id],
        'sessions' => [
            ['from' => '16:00', 'to' => '18:00', 'label' => 'الفترة المسائية'],
            ['from' => '20:00', 'to' => '21:30', 'label' => null],
        ],
    ]);

    expect(AcademicCalendarEvent::sessionsOn('2026-07-08', $this->primary->id))->toHaveCount(2)
        // A day off carries no times, and another stage does not follow this period.
        ->and(AcademicCalendarEvent::sessionsOn('2026-07-10', $this->primary->id))->toBe([])
        ->and(AcademicCalendarEvent::sessionsOn('2026-07-08', $this->middle->id))->toBe([]);
});

it('measures a student attendance against the working days of their own stage', function () {
    period(['weekdays' => [1, 2], 'stage_ids' => [$this->primary->id]]);          // Sun, Mon.
    period(['weekdays' => [1, 2, 3, 4, 5], 'stage_ids' => [$this->middle->id]]);  // Sun to Thu.

    $primaryCircle = Circle::factory()->create(['stage_id' => $this->primary->id]);
    $middleCircle = Circle::factory()->create(['stage_id' => $this->middle->id]);

    Student::factory()->create(['circle_id' => $primaryCircle->id, 'status' => 'active']);
    Student::factory()->create(['circle_id' => $middleCircle->id, 'status' => 'active']);

    [$from, $to] = CircleReportService::resolveRange('this_week');

    $primary = CircleReportService::build(CircleReportService::studentsForCircle($primaryCircle), $from, $to);
    $middle = CircleReportService::build(CircleReportService::studentsForCircle($middleCircle), $from, $to);

    // The week runs Sat 04 to Wed 08: two circle days for the primary stage,
    // four for the middle one.
    expect($primary['totals']['attendance']['working_days'])->toBe(2)
        ->and($middle['totals']['attendance']['working_days'])->toBe(4);
});

it('saves the stages, the named days and the working times from the manager page', function () {
    $this->actingAs(Manager::factory()->create(), 'manager');

    Livewire::test('manager.⚡academic-calendar')
        ->call('createPeriod')
        ->set('hijriFromDate', '2026-09-01')
        ->set('hijriToDate', '2026-09-30')
        ->set('selectedWeekdays', [1, 2])
        ->set('periodStageIds', [(string) $this->primary->id])
        ->set('newPeriodDate', '2026-09-12')
        ->call('addPeriodDate', 'extra')
        ->set('newPeriodDate', '2026-09-14')
        ->call('addPeriodDate', 'excluded')
        ->call('addSession')
        ->set('periodSessions.0.from', '16:00')
        ->set('periodSessions.0.to', '18:00')
        ->set('periodSessions.0.label', '')
        ->call('saveAttendancePeriod')
        ->assertHasNoErrors();

    $period = AcademicCalendarEvent::where('is_attendance_period', true)->latest('id')->first();

    expect($period->stage_ids)->toBe([$this->primary->id])
        ->and($period->extra_dates)->toBe(['2026-09-12'])
        ->and($period->excluded_dates)->toBe(['2026-09-14'])
        ->and($period->sessions)->toBe([['from' => '16:00', 'to' => '18:00', 'label' => '']])
        // The stored count comes from the same rules every other page reads:
        // September's Sundays and Mondays, less the excluded one, plus the extra day.
        ->and($period->day_count)->toBe(count(AcademicCalendarEvent::workingDaysBetween(
            '2026-09-01', '2026-09-30', $this->primary->id
        )));
});

it('refuses a working time that ends before it starts', function () {
    $this->actingAs(Manager::factory()->create(), 'manager');

    Livewire::test('manager.⚡academic-calendar')
        ->call('createPeriod')
        ->set('hijriFromDate', '2026-09-01')
        ->set('hijriToDate', '2026-09-30')
        ->call('addSession')
        ->set('periodSessions.0.from', '18:00')
        ->set('periodSessions.0.to', '16:00')
        ->call('saveAttendancePeriod')
        ->assertHasErrors('periodSessions.0.to');
});

it('takes a date off the other list when it is named on one', function () {
    $this->actingAs(Manager::factory()->create(), 'manager');

    Livewire::test('manager.⚡academic-calendar')
        ->call('createPeriod')
        ->set('newPeriodDate', '2026-09-12')
        ->call('addPeriodDate', 'extra')
        ->assertSet('periodExtraDates', ['2026-09-12'])
        ->set('newPeriodDate', '2026-09-12')
        ->call('addPeriodDate', 'excluded')
        ->assertSet('periodExtraDates', [])
        ->assertSet('periodExcludedDates', ['2026-09-12']);
});

/**
 * The working times are there to be read, not acted on: the attendance page
 * shows the teacher when the circle is due, in place of the session length it
 * used to ask them to type.
 */
it('shows the working times of the circle on the attendance page', function () {
    period([
        'stage_ids' => [$this->middle->id],
        'sessions' => [['from' => '16:00', 'to' => '18:00', 'label' => 'الفترة المسائية']],
    ]);

    $circle = Circle::factory()->create(['stage_id' => $this->middle->id]);
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($circle->id);

    $this->actingAs($teacher, 'teacher');

    Livewire::test(Attendance::class)
        ->set('date', '2026-07-08')
        ->set('selectedCircle', $circle->id)
        ->assertSee('16:00')
        ->assertSee('الفترة المسائية')
        // The session length input is gone with the idea behind it.
        ->assertDontSee('مدة الدفعة');
});

it('reopens a period with everything it was saved with', function () {
    $this->actingAs(Manager::factory()->create(), 'manager');

    $saved = period([
        'stage_ids' => [$this->middle->id],
        'extra_dates' => ['2026-07-11'],
        'excluded_dates' => ['2026-07-06'],
        'sessions' => [['from' => '16:00', 'to' => '18:00', 'label' => 'مسائية']],
    ]);

    Livewire::test('manager.⚡academic-calendar')
        ->call('editPeriod', $saved->id)
        ->assertSet('editingPeriodId', $saved->id)
        ->assertSet('periodStageIds', [(string) $this->middle->id])
        ->assertSet('periodExtraDates', ['2026-07-11'])
        ->assertSet('periodExcludedDates', ['2026-07-06'])
        ->assertSet('periodSessions', [['from' => '16:00', 'to' => '18:00', 'label' => 'مسائية']]);
});
