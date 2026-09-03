<?php

namespace App\Services\Reports;

use App\Models\Task;
use App\Models\User;
use App\Support\HijriDate;
use Illuminate\Support\Collection;

/**
 * How teachers and supervisors are keeping to what is asked of them.
 *
 * Finishing and finishing on time are counted apart, because they are different
 * virtues and a single "done" figure hides which one a person has. A man who
 * closes every task a fortnight late reads as perfect by completion alone, and
 * the lateness is the whole of what his supervisor needs to see.
 *
 * Overdue is likewise kept apart from merely open: a task due next week that is
 * unfinished is work in hand; one due last month is a problem.
 */
class TasksReport implements Report
{
    /** Gathered by the person, by the kind of work, or by the office held. */
    public const BY_ASSIGNEE = 'student';

    public const BY_CATEGORY = 'circle';

    public const BY_ROLE = 'stage';

    public const BY_ALL = 'centre';

    public function key(): string
    {
        return 'tasks';
    }

    public function label(): string
    {
        return 'المهام وأداؤها';
    }

    public function description(): string
    {
        return 'ما أُسند من مهام: ما أُنجز، وما أُنجز في موعده، وما تأخّر، وما تجاوز موعده ولم يُنجز.';
    }

    /** @return array<string, string> */
    public function groupings(): array
    {
        return [
            self::BY_ASSIGNEE => 'كل مُسنَد إليه على حدة',
            self::BY_CATEGORY => 'مجموعة لكل تصنيف',
            self::BY_ROLE => 'مجموعة لكل دور',
            self::BY_ALL => 'الكل في سطر',
        ];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $tasks = $query->scope->applyToTasks(
            Task::query()->with('category')->whereBetween('created_at', [
                $query->from->toDateString().' 00:00:00',
                $query->to->toDateString().' 23:59:59',
            ]),
        )->get();

        $people = $this->namesFor($tasks);
        $groups = [];

        foreach ($tasks as $task) {
            $label = $this->labelFor($task, $query->groupBy, $people);

            $groups[$label] ??= [
                'name' => $label, 'total' => 0, 'done' => 0,
                'on_time' => 0, 'late' => 0, 'overdue' => 0, 'undated' => 0,
            ];

            $groups[$label]['total']++;
            $groups[$label]['undated'] += $task->due_date === null ? 1 : 0;

            if ($task->isDone()) {
                $groups[$label]['done']++;
                $groups[$label][$task->wasLate() ? 'late' : 'on_time']++;
            } elseif ($task->isOverdue()) {
                $groups[$label]['overdue']++;
            }
        }

        $rows = array_values($groups);

        foreach ($rows as &$row) {
            $row['open'] = $row['total'] - $row['done'];
            $row['completion'] = $this->rate($row['done'], $row['total']);
            // Punctuality is measured against what was finished, not against
            // everything: a task still open is not yet late in this sense.
            $row['punctuality'] = $this->rate($row['on_time'], $row['done']);
        }
        unset($row);

        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        $totals = ['name' => 'الإجمالي'];

        foreach (['total', 'done', 'on_time', 'late', 'overdue', 'undated', 'open'] as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        $totals['completion'] = $this->rate($totals['done'], $totals['total']);
        $totals['punctuality'] = $this->rate($totals['on_time'], $totals['done']);

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'المُسنَد إليه'],
                ['key' => 'total', 'label' => 'مهام', 'numeric' => true],
                ['key' => 'done', 'label' => 'أُنجزت', 'numeric' => true],
                ['key' => 'completion', 'label' => 'نسبة الإنجاز', 'numeric' => true],
                ['key' => 'on_time', 'label' => 'في موعدها', 'numeric' => true],
                ['key' => 'late', 'label' => 'متأخرة', 'numeric' => true],
                ['key' => 'punctuality', 'label' => 'نسبة الالتزام', 'numeric' => true],
                ['key' => 'open', 'label' => 'قائمة', 'numeric' => true],
                ['key' => 'overdue', 'label' => 'تجاوزت موعدها', 'numeric' => true],
                ['key' => 'undated', 'label' => 'بلا موعد', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }

    /**
     * The names of everyone a task was given to, read in one query.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<int, User>
     */
    private function namesFor($tasks): array
    {
        $ids = $tasks->pluck('assigned_to_id')->filter()->unique();

        return $ids->isEmpty()
            ? []
            : User::whereIn('id', $ids)->with('roles')->get()->keyBy('id')->all();
    }

    /** @param  array<int, User>  $people */
    private function labelFor(Task $task, string $groupBy, array $people): string
    {
        $person = $people[$task->assigned_to_id] ?? null;

        return match ($groupBy) {
            self::BY_CATEGORY => $task->category?->name ?? 'بلا تصنيف',
            self::BY_ROLE => $this->roleLabel($person),
            self::BY_ALL => 'الكل',
            default => $person?->name ?? 'بلا مُسنَد إليه',
        };
    }

    private function roleLabel(?User $person): string
    {
        $labels = ['manager' => 'مدير المركز', 'supervisor' => 'مشرف دفعة', 'teacher' => 'معلم دفعة'];

        foreach ($labels as $key => $label) {
            if ($person?->roles->contains('role', $key)) {
                return $label;
            }
        }

        return 'غير ذلك';
    }

    private function rate(int $part, int $whole): string
    {
        return $whole > 0 ? round($part / $whole * 100).'%' : '—';
    }
}
