<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskSeries;
use App\Models\TaskTemplate;
use App\Models\Teacher;
use App\Models\User;
use App\Support\RoleHierarchy;
use App\Support\Scope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything that turns a list of tasks into work being followed.
 *
 * Five things live here because they are one thing seen from five sides: a task
 * raised from a pattern, a task raised for a whole office at once, a set raised
 * from a template, a task that has gone past its date and needs somebody senior
 * told, and the record of every hand that touched any of them.
 *
 * They are together because they lean on each other. A template writes steps as
 * well as titles; a series assigned to an office raises one task per person and
 * has to know who those people are; an escalation needs the chain of
 * responsibility that assignment already speaks.
 */
class TaskFollowUpService
{
    /**
     * Raise whatever falls due on a day, from every active pattern.
     *
     * Idempotent by the day it stands for: running twice on Thursday makes one
     * Thursday's work, which matters because a scheduler that missed a night is
     * caught up by simply running it again.
     *
     * @return int how many tasks were raised
     */
    public function raiseDue(?CarbonInterface $day = null): int
    {
        $day = $day ? Carbon::parse($day)->startOfDay() : Carbon::today('Asia/Riyadh');
        $raised = 0;

        foreach (TaskSeries::where('is_active', true)->get() as $series) {
            if (! $series->fallsOn($day)) {
                continue;
            }

            $already = Task::where('task_series_id', $series->id)
                ->whereDate('occurs_on', $day->toDateString())
                ->exists();

            if ($already) {
                continue;
            }

            // Every task must name who asked for it, and the asker of a
            // repeating one is whoever set the pattern. A series without an
            // author cannot raise anything, and the nightly run must step over
            // it rather than die on it and leave every other pattern unraised.
            if (! $series->created_by_id) {
                continue;
            }

            $raised += count($this->raiseFromSeries($series, $day));
        }

        return $raised;
    }

    /**
     * One occurrence of a series — one task, or one per person of an office.
     *
     * @return array<int, Task>
     */
    public function raiseFromSeries(TaskSeries $series, CarbonInterface $day): array
    {
        $batch = (string) Str::uuid();

        $shape = [
            'title' => $series->title,
            'description' => $series->description,
            'task_category_id' => $series->task_category_id,
            'due_date' => $day->toDateString(),
            'status' => 'pending',
            'stage' => Task::TODO,
            'task_series_id' => $series->id,
            'occurs_on' => $day->toDateString(),
            'batch_key' => $batch,
            'remind_days_before' => $series->remind_days_before,
            'created_by_type' => $series->created_by_type,
            'created_by_id' => $series->created_by_id,
        ];

        if ($series->assign_to_role) {
            return $this->raiseForEach(
                $shape,
                $series->assign_to_role,
                $series->assign_scope_type,
                $series->assign_scope_ids ?? [],
            );
        }

        return [$this->raise($shape + [
            'assigned_to_type' => $series->assigned_to_type,
            'assigned_to_id' => $series->assigned_to_id,
        ])];
    }

    /**
     * One task each, for everybody holding an office within a reach.
     *
     * One task shared between six teachers is one task nobody finished. Six
     * tasks with one key between them is six people answerable and one act to
     * look at, which is what a supervisor actually needs.
     *
     * @param  array<string, mixed>  $shape
     * @param  array<int, int>  $scopeIds
     * @return array<int, Task>
     */
    public function raiseForEach(array $shape, string $role, ?string $scopeType = null, array $scopeIds = []): array
    {
        $shape['batch_key'] ??= (string) Str::uuid();
        $made = [];

        foreach ($this->peopleHolding($role, $scopeType, $scopeIds) as $person) {
            $made[] = $this->raise($shape + [
                'assigned_to_type' => $role,
                'assigned_to_id' => $person->id,
            ]);
        }

        return $made;
    }

    /**
     * Everybody holding an office, narrowed to programmes or cohorts.
     *
     * @param  array<int, int>  $scopeIds
     * @return Collection<int, User>
     */
    public function peopleHolding(string $role, ?string $scopeType = null, array $scopeIds = []): Collection
    {
        $model = match ($role) {
            'teacher' => Teacher::class,
            'supervisor' => Supervisor::class,
            'student' => Student::class,
            'guardian' => Guardian::class,
            'manager' => Manager::class,
            default => null,
        };

        if (! $model) {
            return collect();
        }

        // Approval lives on the holding of the role, not on the person: a man
        // may be an approved teacher and an unapproved supervisor at once. The
        // model reads it back as an attribute, which is why a plain `where` on
        // it finds nothing at all.
        $query = $model::query()->whereRoleState(fn ($q) => $q->where('is_approved', true));

        if ($scopeIds === [] || ! $scopeType) {
            return $query->get();
        }

        return match ([$role, $scopeType]) {
            ['teacher', 'stages'] => $query->whereHas('circles', fn ($q) => $q->whereIn('stage_id', $scopeIds))->get(),
            ['teacher', 'circles'] => $query->whereHas('circles', fn ($q) => $q->whereIn('circles.id', $scopeIds))->get(),
            ['supervisor', 'stages'] => $query->whereHas('stages', fn ($q) => $q->whereIn('stages.id', $scopeIds))->get(),
            ['student', 'stages'] => $query->whereIn('stage_id', $scopeIds)->get(),
            ['student', 'circles'] => $query->whereIn('circle_id', $scopeIds)->get(),
            default => $query->get(),
        };
    }

    /**
     * Raise a whole template against a day.
     *
     * Offsets are read from that day, so «three days before» stays three days
     * before whichever term it is applied to.
     *
     * @return array<int, Task>
     */
    public function applyTemplate(
        TaskTemplate $template,
        CarbonInterface $anchor,
        ?User $author = null,
        ?string $authorRole = null,
        ?string $scopeType = null,
        array $scopeIds = [],
    ): array {
        $batch = (string) Str::uuid();
        $made = [];

        foreach ($template->items as $item) {
            $shape = [
                'title' => $item->title,
                'description' => $item->description,
                'task_category_id' => $item->task_category_id,
                'due_date' => Carbon::parse($anchor)->addDays($item->due_offset_days)->toDateString(),
                'status' => 'pending',
                'stage' => Task::TODO,
                'batch_key' => $batch,
                'created_by_type' => $authorRole,
                'created_by_id' => $author?->id,
            ];

            $tasks = $item->assign_to_role
                ? $this->raiseForEach($shape, $item->assign_to_role, $scopeType, $scopeIds)
                : [$this->raise($shape + ['assigned_to_type' => $authorRole, 'assigned_to_id' => $author?->id])];

            foreach ($tasks as $task) {
                foreach ($item->steps ?? [] as $index => $step) {
                    $task->steps()->create(['title' => $step, 'sort_order' => $index]);
                }

                $made[] = $task;
            }
        }

        return $made;
    }

    /**
     * Tell somebody senior about work that has gone past its date.
     *
     * Once per task, not every night: a man told the same thing seven times
     * stops reading the seventh, and the seventh is the one that mattered.
     *
     * The senior is found through the same chain that decides who may assign to
     * whom, so the person told is the person who could have asked for it.
     *
     * @return int how many were escalated
     */
    public function escalateOverdue(?CarbonInterface $on = null): int
    {
        $on = $on ? Carbon::parse($on)->startOfDay() : Carbon::today('Asia/Riyadh');

        $overdue = Task::query()
            ->whereNotIn('status', Task::DONE)
            ->whereNull('escalated_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $on->toDateString())
            ->get();

        $count = 0;

        foreach ($overdue as $task) {
            $senior = $this->seniorOf($task);

            $task->forceFill(['escalated_at' => now()])->save();

            $this->record($task, null, null, TaskActivity::ESCALATED, null, $senior?->name ?? __('لا أحد فوقه'));

            $count++;
        }

        return $count;
    }

    /**
     * Who is told when a task slips.
     *
     * The office above the one it was given to, and the person holding it over
     * the reach this task's owner sits in — his own supervisor, not any.
     */
    public function seniorOf(Task $task): ?User
    {
        $role = $task->assigned_to_type;

        if (! $role) {
            return null;
        }

        $above = collect(RoleHierarchy::map())
            ->filter(fn (array $carries) => in_array($role, $carries, true))
            ->keys()
            ->first();

        if (! $above) {
            return null;
        }

        $owner = User::find($task->assigned_to_id);

        if (! $owner) {
            return null;
        }

        return $this->peopleHolding($above)
            ->first(fn (User $senior) => $this->reaches($senior, $above, $owner, $role));
    }

    /**
     * Whether one person's office covers another.
     *
     * Asked through `Scope`, so it answers by the same reach that decides what
     * he may see — and a manager narrowed to one programme is senior only to
     * the people inside it, which is the whole point of having narrowed him.
     */
    private function reaches(User $senior, string $seniorRole, User $owner, string $ownerRole): bool
    {
        $scope = Scope::for($senior, $seniorRole);

        if ($scope->reachesAll()) {
            return true;
        }

        $circles = $scope->circleIds();
        $stages = $scope->stageIds();

        return match ($ownerRole) {
            'student' => $circles === null || $circles->contains($owner->circle_id),
            'teacher' => $circles === null
                || $owner->circles()->pluck('circles.id')->intersect($circles)->isNotEmpty(),
            'supervisor' => $stages === null
                || $owner->stages()->pluck('stages.id')->intersect($stages)->isNotEmpty(),
            default => false,
        };
    }

    /** Write a task, and open its record with the fact that it was written. */
    public function raise(array $shape): Task
    {
        $task = Task::create($shape);

        $this->record($task, $shape['created_by_id'] ?? null, $shape['created_by_type'] ?? null, TaskActivity::CREATED);

        return $task;
    }

    /** Add one line to a task's record. */
    public function record(
        Task $task,
        ?int $actorId,
        ?string $actorRole,
        string $action,
        ?string $from = null,
        ?string $to = null,
    ): TaskActivity {
        return $task->activities()->create([
            'actor_id' => $actorId,
            'actor_type' => $actorRole,
            'action' => $action,
            'from_value' => $from,
            'to_value' => $to,
        ]);
    }
}
