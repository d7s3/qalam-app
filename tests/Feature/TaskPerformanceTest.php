<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Reports\ReportCatalogue;
use App\Services\Reports\ReportQuery;
use App\Services\Reports\TasksReport;
use App\Support\Scope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Judging teachers and supervisors on what was asked of them.
 *
 * Finishing and finishing on time are two virtues, and a single "done" figure
 * hides which one a person has: a man who closes every task a fortnight late
 * reads as perfect by completion alone.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-20 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);

    $this->teacher = Teacher::factory()->create(['name' => 'أستاذ سعيد']);
    $this->teacher->circles()->attach($this->circle->id);

    $this->supervisor = Supervisor::factory()->create(['name' => 'المشرف خالد']);
    $this->supervisor->stages()->attach($this->stage->id);

    $this->manager = Manager::factory()->create(['name' => 'المدير']);

    $this->category = TaskCategory::create(['name' => 'متابعة الدفعات', 'color' => '#7a2727']);
});

function makeTask(User $for, ?string $due, ?string $completedOn, ?int $categoryId = null): Task
{
    $task = Task::create([
        'title' => 'مهمة',
        'due_date' => $due,
        'status' => $completedOn ? 'completed' : 'pending',
        'task_category_id' => $categoryId,
        'assigned_to_id' => $for->id,
        'assigned_to_type' => $for::class,
        'created_by_id' => test()->manager->id,
        'created_by_type' => Manager::class,
        'created_at' => '2026-09-01 08:00:00',
    ]);

    if ($completedOn) {
        // Stamped past the model's own hook, to place the finishing precisely.
        $task->forceFill(['completed_at' => $completedOn])->saveQuietly();
    }

    return $task->fresh();
}

function tasksFor(User $user, string $role, string $groupBy = TasksReport::BY_ASSIGNEE): array
{
    return ReportCatalogue::find('tasks')->run(new ReportQuery(
        scope: Scope::for($user, $role),
        from: Carbon::parse('2026-09-01'),
        to: Carbon::parse('2026-09-30'),
        groupBy: $groupBy,
    ))->rows;
}

it('stamps the moment a task is finished', function () {
    $task = Task::create([
        'title' => 'مهمة', 'due_date' => '2026-09-10', 'status' => 'pending',
        'assigned_to_id' => $this->teacher->id, 'assigned_to_type' => Teacher::class,
        'created_by_id' => $this->manager->id, 'created_by_type' => Manager::class,
    ]);

    expect($task->completed_at)->toBeNull();

    $task->update(['status' => 'completed']);

    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('takes the stamp away when a task is reopened', function () {
    $task = makeTask($this->teacher, '2026-09-10', '2026-09-09 10:00:00');
    expect($task->completed_at)->not->toBeNull();

    $task->update(['status' => 'pending']);

    expect($task->fresh()->completed_at)->toBeNull();
});

it('counts finishing and finishing on time apart', function () {
    makeTask($this->teacher, '2026-09-10', '2026-09-08 10:00:00');   // on time
    makeTask($this->teacher, '2026-09-10', '2026-09-14 10:00:00');   // late
    makeTask($this->teacher, '2026-09-05', null);                     // overdue
    makeTask($this->teacher, '2026-09-28', null);                     // in hand

    $row = collect(tasksFor($this->manager, 'manager'))->firstWhere('name', 'أستاذ سعيد');

    expect($row['total'])->toBe(4)
        ->and($row['done'])->toBe(2)
        ->and($row['on_time'])->toBe(1)
        ->and($row['late'])->toBe(1)
        ->and($row['overdue'])->toBe(1)
        ->and($row['open'])->toBe(2)
        ->and($row['completion'])->toBe('50%')
        // Punctuality is measured against what was finished, not everything.
        ->and($row['punctuality'])->toBe('50%');
});

it('never calls a task late when nothing was promised', function () {
    makeTask($this->teacher, null, '2026-09-14 10:00:00');

    $row = collect(tasksFor($this->manager, 'manager'))->firstWhere('name', 'أستاذ سعيد');

    expect($row['late'])->toBe(0)
        ->and($row['on_time'])->toBe(1)
        ->and($row['undated'])->toBe(1);
});

it('gathers by the kind of work', function () {
    makeTask($this->teacher, '2026-09-10', '2026-09-08 10:00:00', $this->category->id);
    makeTask($this->supervisor, '2026-09-10', null);

    $names = array_column(tasksFor($this->manager, 'manager', TasksReport::BY_CATEGORY), 'name');

    expect($names)->toEqualCanonicalizing(['متابعة الدفعات', 'بلا تصنيف']);
});

it('gathers by the office held', function () {
    makeTask($this->teacher, '2026-09-10', null);
    makeTask($this->supervisor, '2026-09-10', null);

    $names = array_column(tasksFor($this->manager, 'manager', TasksReport::BY_ROLE), 'name');

    expect($names)->toEqualCanonicalizing(['معلم دفعة', 'مشرف دفعة']);
});

describe('who may follow whose tasks', function () {
    beforeEach(function () {
        $this->stranger = Teacher::factory()->create(['name' => 'أستاذ غريب']);
        $this->stranger->circles()->attach(
            Circle::factory()->create(['stage_id' => Stage::factory()->create()->id])->id,
        );

        makeTask($this->teacher, '2026-09-10', null);
        makeTask($this->stranger, '2026-09-10', null);
        makeTask($this->supervisor, '2026-09-10', null);
    });

    it('shows a centre manager everyone', function () {
        expect(array_column(tasksFor($this->manager, 'manager'), 'name'))
            ->toEqualCanonicalizing(['أستاذ سعيد', 'أستاذ غريب', 'المشرف خالد']);
    });

    it('shows a cohort supervisor his own teachers and himself', function () {
        expect(array_column(tasksFor($this->supervisor, 'supervisor'), 'name'))
            ->toEqualCanonicalizing(['أستاذ سعيد', 'المشرف خالد']);
    });

    it('shows a cohort teacher his own alone', function () {
        expect(array_column(tasksFor($this->teacher, 'teacher'), 'name'))->toBe(['أستاذ سعيد']);
    });
});

it('offers the axes that suit tasks, not those that suit students', function () {
    $groupings = ReportCatalogue::find('tasks')->groupings();

    expect(array_values($groupings))->toBe([
        'كل مُسنَد إليه على حدة', 'مجموعة لكل تصنيف', 'مجموعة لكل دور', 'الكل في سطر',
    ]);
});
