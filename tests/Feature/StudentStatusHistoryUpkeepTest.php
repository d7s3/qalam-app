<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\StudentStatusService;
use Illuminate\Routing\Route;

/**
 * A student's history is a run of periods, each ending where the next begins.
 * Taking a wrong one out used to leave a hole in the run; and a change was
 * signed by whichever office came first in a fixed walk — the manager — rather
 * than the one it was made from.
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-06-25 10:00:00');

    $this->student = Student::factory()->create([
        'circle_id' => Circle::factory()->create(['stage_id' => Stage::factory()->create()->id])->id,
        'status' => 'active',
    ]);

    StudentStatusService::changeStatus($this->student, 'active', '2026-06-01');
    StudentStatusService::changeStatus($this->student, 'suspended', '2026-06-10');
    StudentStatusService::changeStatus($this->student, 'active', '2026-06-20');
});

/** @return list<array{0: string, 1: string, 2: ?string}> */
function periodsOf(Student $student): array
{
    return $student->statusHistories()->reorder()->orderBy('start_date')->get()
        ->map(fn ($period) => [$period->status, $period->start_date->toDateString(), $period->end_date?->toDateString()])
        ->all();
}

/** Make the request look as if it came from the named page. */
function fromPage(string $routeName): void
{
    $route = (new Route('GET', '/'.str_replace('.', '/', $routeName), []))->name($routeName);

    request()->setRouteResolver(fn () => $route);
}

it('joins up the periods either side of one taken out', function () {
    $suspension = $this->student->statusHistories()->where('status', 'suspended')->sole();

    StudentStatusService::deleteHistoryEntry($this->student, $suspension->id);

    expect(periodsOf($this->student))->toBe([
        ['active', '2026-06-01', '2026-06-20'],
        ['active', '2026-06-20', null],
    ]);
});

it('opens again the period before the last one taken out, and the student is back in it', function () {
    $latest = $this->student->statusHistories()->whereDate('start_date', '2026-06-20')->sole();

    StudentStatusService::deleteHistoryEntry($this->student, $latest->id);

    expect(periodsOf($this->student))->toBe([
        ['active', '2026-06-01', '2026-06-10'],
        ['suspended', '2026-06-10', null],
    ])->and($this->student->refresh()->status)->toBe('suspended');
});

it('signs a change with the office it was made from, not the first one signed in', function (string $page, string $office) {
    $teacher = Teacher::factory()->create(['name' => 'المعلم']);
    $manager = Manager::factory()->create(['name' => 'المدير']);

    $this->actingAs($teacher, 'teacher');
    $this->actingAs($manager, 'manager');
    fromPage($page);

    StudentStatusService::changeStatus($this->student, 'inactive', null, 'أُخرج من الدفعة');

    $period = $this->student->statusHistories()->where('status', 'inactive')->sole();

    expect([$period->changed_by_role, $period->changed_by_name])->toBe([$office, $office === 'teacher' ? 'المعلم' : 'المدير']);
})->with([
    'from a teacher\'s page' => ['teacher.students', 'teacher'],
    'from a manager\'s page' => ['manager.students', 'manager'],
]);
