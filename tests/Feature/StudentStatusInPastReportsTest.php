<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Services\CircleReportService;
use App\Services\StudentStatusService;
use Carbon\Carbon;

/**
 * A report on a past range lists the students who were مشارك in it, not the
 * ones who are today. A student suspended since used to drop out of the very
 * weeks he had attended, from the cohort's report and the programme's.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-06-25 10:00:00');

    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->suspendedSince = Student::factory()->create(['name' => 'أوقف لاحقاً', 'circle_id' => $this->cohort->id, 'status' => 'active']);
    StudentStatusService::changeStatus($this->suspendedSince, 'active', '2026-06-01');
    StudentStatusService::changeStatus($this->suspendedSince, 'suspended', '2026-06-10');

    $this->alwaysThere = Student::factory()->create(['name' => 'مشارك دائماً', 'circle_id' => $this->cohort->id, 'status' => 'active']);
});

it('keeps a student suspended since in the cohort\'s report on the weeks he attended', function () {
    $names = fn (Carbon $from, Carbon $to) => CircleReportService::studentsForCircle($this->cohort, $from, $to)->pluck('name')->all();

    expect($names(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-09')))->toBe(['أوقف لاحقاً', 'مشارك دائماً'])
        ->and($names(Carbon::parse('2026-06-15'), Carbon::parse('2026-06-20')))->toBe(['مشارك دائماً']);
});

it('keeps him in the programme\'s report on those weeks too', function () {
    $names = CircleReportService::studentsForStage($this->programme, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-09'))->pluck('name')->all();

    expect($names)->toContain('أوقف لاحقاً');
});

it('still lists only today\'s مشاركين when no range is given', function () {
    expect(CircleReportService::studentsForCircle($this->cohort)->pluck('name')->all())->toBe(['مشارك دائماً']);
});
