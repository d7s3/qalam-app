<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\ExamLevel;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\UserRole;
use App\Support\Scope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A programme's manager acts on his own programme, and only on it.
 *
 * His lists were narrowed to what he reaches, but the buttons beside them
 * found their record by bare id: an id from the next programme — typed into
 * the browser, or left from a link — was approved, edited or deleted as
 * readily as his own. The centre's manager is unchanged.
 */
beforeEach(function () {
    Scope::forget();

    $this->mine = Stage::factory()->create(['name' => 'برنامجي']);
    $this->theirs = Stage::factory()->create(['name' => 'برنامجهم']);
    $this->theirCohort = Circle::factory()->create(['stage_id' => $this->theirs->id]);
    Circle::factory()->create(['stage_id' => $this->mine->id]);

    $this->theirTeacher = Teacher::factory()->create(['is_approved' => true]);
    $this->theirTeacher->circles()->attach($this->theirCohort->id);
    $this->theirSupervisor = Supervisor::factory()->create(['is_approved' => true]);
    $this->theirSupervisor->stages()->attach($this->theirs->id);
    $this->theirGuardian = Guardian::factory()->create(['is_approved' => true]);
    $this->theirStudent = Student::factory()->create([
        'circle_id' => $this->theirCohort->id,
        'stage_id' => $this->theirs->id,
        'guardian_id' => $this->theirGuardian->id,
        'is_approved' => true,
    ]);
    $this->emptyCohort = Circle::factory()->create(['stage_id' => $this->theirs->id]);

    $this->director = Manager::factory()->create();
    $this->director->roles()->where('role', 'manager')->update([
        'scope_type' => UserRole::SCOPE_STAGES,
        'scope_ids' => [$this->mine->id],
    ]);
    $this->director->load('roles');

    $this->centre = Manager::factory()->create();
});

dataset('another programme\'s records', [
    'معلم' => ['manager.teachers', 'theirTeacher'],
    'مشرف' => ['manager.supervisors', 'theirSupervisor'],
    'طالب' => ['manager.students', 'theirStudent'],
    'ولي أمر' => ['manager.guardians', 'theirGuardian'],
    'دفعة' => ['manager.circles', 'emptyCohort'],
]);

it('does not delete a record of another programme', function (string $screen, string $record) {
    $target = $this->{$record};

    try {
        Livewire::actingAs($this->director, 'manager')->test($screen)->call('delete', $target->id);
    } catch (ModelNotFoundException) {
        // Refused by not finding it — as good as a refusal in words.
    }

    expect($target::query()->whereKey($target->id)->exists())->toBeTrue();
})->with('another programme\'s records');

it('still lets the centre\'s manager delete it', function (string $screen, string $record) {
    $target = $this->{$record};

    Livewire::actingAs($this->centre, 'manager')->test($screen)->call('delete', $target->id);

    expect($target::query()->whereKey($target->id)->exists())->toBeFalse();
})->with('another programme\'s records');

describe('the calendar', function () {
    function periodFor(array $stageIds, $author): AcademicCalendarEvent
    {
        return AcademicCalendarEvent::create([
            'event_name' => 'فترة دوام الدفعات',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_attendance_period' => true,
            'weekdays' => [1, 2, 3, 4, 5],
            'stage_ids' => $stageIds,
            'created_by_id' => $author->id,
            'created_by_type' => get_class($author),
        ]);
    }

    it('does not let a programme\'s manager remove the centre\'s or another programme\'s working period', function (string $whose) {
        $period = periodFor($whose === 'centre' ? [] : [$this->theirs->id], $this->centre);

        Livewire::actingAs($this->director, 'manager')
            ->test('manager.academic-calendar')
            ->call('deletePeriod', $period->id)
            ->assertForbidden();

        expect(AcademicCalendarEvent::whereKey($period->id)->exists())->toBeTrue();
    })->with(['centre', 'theirs']);

    it('lets him remove his own programme\'s period, and the centre\'s manager any', function () {
        $his = periodFor([$this->mine->id], $this->centre);
        $theirs = periodFor([$this->theirs->id], $this->centre);

        Livewire::actingAs($this->director, 'manager')->test('manager.academic-calendar')->call('deletePeriod', $his->id);
        Scope::forget();
        Livewire::actingAs($this->centre, 'manager')->test('manager.academic-calendar')->call('deletePeriod', $theirs->id);

        expect(AcademicCalendarEvent::whereKey([$his->id, $theirs->id])->exists())->toBeFalse();
    });

    it('does not let him write a period for another programme, or for the whole centre', function (array $stages) {
        Livewire::actingAs($this->director, 'manager')
            ->test('manager.academic-calendar')
            ->set('hijriFromDate', '2026-09-01')
            ->set('hijriToDate', '2026-09-30')
            ->set('selectedWeekdays', [1, 2, 3])
            ->set('periodStageIds', array_map(fn ($key) => (string) $this->{$key}->id, $stages))
            ->call('saveAttendancePeriod')
            ->assertHasErrors('periodStageIds');

        expect(AcademicCalendarEvent::where('is_attendance_period', true)->count())->toBe(0);
    })->with(['another programme' => [['theirs']], 'the whole centre' => [[]]]);
});

describe('the students\' exams', function () {
    beforeEach(function () {
        $level = ExamLevel::create(['name' => 'المستوى الأول', 'direction' => 'forward']);

        $this->theirExam = StudentExam::create([
            'student_id' => $this->theirStudent->id,
            'exam_level_id' => $level->id,
            'date_time' => now(),
            'status' => 'pending',
        ]);
    });

    it('shows a programme\'s manager no exam of another programme', function () {
        Livewire::actingAs($this->director, 'manager')
            ->test('manager.student-exams')
            ->assertDontSee($this->theirStudent->name);
    });

    it('does not let him open or delete one', function (string $action) {
        expect(fn () => Livewire::actingAs($this->director, 'manager')
            ->test('manager.student-exams')
            ->call($action, $this->theirExam->id))
            ->toThrow(ModelNotFoundException::class);

        expect($this->theirExam->fresh())->not->toBeNull();
    })->with(['edit', 'delete']);

    it('still shows and lets the centre\'s manager delete it', function () {
        Livewire::actingAs($this->centre, 'manager')
            ->test('manager.student-exams')
            ->assertSee($this->theirStudent->name)
            ->call('delete', $this->theirExam->id);

        expect($this->theirExam->fresh())->toBeNull();
    });
});
