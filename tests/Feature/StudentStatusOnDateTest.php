<?php

use App\Livewire\Teacher\Attendance as TeacherAttendance;
use App\Livewire\Teacher\AttendanceSheet;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\CircleReportService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Livewire;

/**
 * Whether a student counted on a day is read from his history, and where the
 * history says nothing yet, from his own status column — in the register and
 * in the reports alike. The reports used to call every student without a row
 * active whatever his column said, so a student تحت التسجيل was off the
 * register while his attendance counted in the reports.
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-10-07 09:00:00'); // A Wednesday.

    $this->cohort = Circle::factory()->create(['stage_id' => Stage::factory()->create()->id]);
    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);
});

function studentWithoutHistory(Circle $cohort, ?string $status): Student
{
    return Student::factory()->create([
        'circle_id' => $cohort->id,
        'status' => $status,
        'is_approved' => true,
        'joined_at' => '2026-09-01',
    ]);
}

it('leaves out of the reports a student تحت التسجيل whom the register leaves out', function () {
    $registering = studentWithoutHistory($this->cohort, 'registering');
    $active = studentWithoutHistory($this->cohort, 'active');

    foreach ([$registering, $active] as $student) {
        Attendance::create(['student_id' => $student->id, 'circle_id' => $this->cohort->id, 'date' => '2026-10-07', 'status' => 'present']);
    }

    $counted = Attendance::whereRaw(Attendance::activeStatusOnDateSql())->pluck('student_id')->all();

    expect($counted)->toBe([$active->id]);

    $this->actingAs($this->teacher, 'teacher');

    Livewire::test(TeacherAttendance::class)
        ->set('selectedCircle', $this->cohort->id)
        ->assertSee($active->name)
        ->assertDontSee($registering->name);
});

it('expects no days of a student تحت التسجيل in a cohort report', function () {
    $registering = studentWithoutHistory($this->cohort, 'registering');
    $active = studentWithoutHistory($this->cohort, 'active');

    $report = CircleReportService::build(
        new Collection([$registering, $active]),
        Carbon\Carbon::parse('2026-10-04'),
        Carbon\Carbon::parse('2026-10-07'),
    );

    $expected = $report['perStudent']->keyBy(fn (array $row) => $row['student']->id);

    expect($expected[$registering->id]['expected_days'])->toBe(0)
        ->and($expected[$active->id]['expected_days'])->toBeGreaterThan(0);
});

it('counts a student whose status column is empty as مشارك, in the register and the reports', function () {
    $unstated = studentWithoutHistory($this->cohort, null);

    Attendance::create(['student_id' => $unstated->id, 'circle_id' => $this->cohort->id, 'date' => '2026-10-07', 'status' => 'present']);

    expect(Attendance::whereRaw(Attendance::activeStatusOnDateSql())->count())->toBe(1);

    $this->actingAs($this->teacher, 'teacher');

    // His empty column used to break the monthly sheet outright: the date's
    // status was typed as a string and fell back to the column.
    Livewire::test(AttendanceSheet::class, ['circleId' => $this->cohort->id])
        ->assertOk()
        ->assertSee($unstated->name);
});
