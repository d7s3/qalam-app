<?php

namespace App\Services\Reports;

use App\Models\Circle;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\Teacher;
use App\Support\HijriDate;
use Illuminate\Support\Collection;

/**
 * How supervisors are following the teachers under them.
 *
 * Not what their teachers achieved — that is the teachers' own report — but
 * whether the supervisor was there: whether he asked anything of them, of how
 * many of them, and whether what he asked was seen through.
 *
 * The figure that matters most is the plainest: how many of his teachers he
 * asked nothing of at all. A supervisor with a fine completion rate over two
 * teachers out of nine is not supervising nine.
 */
class SupervisionReport implements Report
{
    public function key(): string
    {
        return 'supervision';
    }

    public function label(): string
    {
        return 'متابعة المشرفين لمعلميهم';
    }

    public function description(): string
    {
        return 'ما أسنده كل مشرف إلى معلميه، وكم معلماً لم يُسند إليه شيئاً، وما أُنجز مما أسنده.';
    }

    /** @return array<string, string> */
    public function groupings(): array
    {
        return ['student' => 'كل مشرف على حدة'];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $reachableStages = $this->stagesInReach($query);

        $supervisors = Supervisor::query()
            ->with('stages')
            ->when($reachableStages !== null, fn ($q) => $q->whereHas(
                'stages',
                fn ($s) => $s->whereIn('stages.id', $reachableStages),
            ))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($supervisors as $supervisor) {
            $stageIds = $supervisor->stages->pluck('id');
            $circleIds = Circle::whereIn('stage_id', $stageIds)->pluck('id');

            $teachers = Teacher::whereHas('circles', fn ($q) => $q->whereIn('circles.id', $circleIds))
                ->pluck('id');

            $assigned = Task::where('created_by_id', $supervisor->id)
                ->whereIn('assigned_to_id', $teachers)
                ->whereBetween('created_at', [
                    $query->from->toDateString().' 00:00:00',
                    $query->to->toDateString().' 23:59:59',
                ])
                ->get();

            $followed = $assigned->pluck('assigned_to_id')->unique()->count();
            $done = $assigned->filter(fn (Task $task) => $task->isDone())->count();
            $onTime = $assigned->filter(fn (Task $task) => $task->isDone() && ! $task->wasLate())->count();

            $rows[] = [
                'name' => $supervisor->name,
                'teachers' => $teachers->count(),
                'followed' => $followed,
                'untouched' => max(0, $teachers->count() - $followed),
                'assigned' => $assigned->count(),
                'done' => $done,
                'on_time' => $onTime,
                'coverage' => $this->rate($followed, $teachers->count()),
                'completion' => $this->rate($done, $assigned->count()),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['untouched'] <=> $a['untouched']);

        $totals = ['name' => 'الإجمالي'];

        foreach (['teachers', 'followed', 'untouched', 'assigned', 'done', 'on_time'] as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        $totals['coverage'] = $this->rate($totals['followed'], $totals['teachers']);
        $totals['completion'] = $this->rate($totals['done'], $totals['assigned']);

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'المشرف'],
                ['key' => 'teachers', 'label' => 'معلموه', 'numeric' => true],
                ['key' => 'followed', 'label' => 'تابعهم', 'numeric' => true],
                ['key' => 'untouched', 'label' => 'لم يُسند إليهم', 'numeric' => true],
                ['key' => 'coverage', 'label' => 'نسبة التغطية', 'numeric' => true],
                ['key' => 'assigned', 'label' => 'مهام أسندها', 'numeric' => true],
                ['key' => 'done', 'label' => 'أُنجزت', 'numeric' => true],
                ['key' => 'on_time', 'label' => 'في موعدها', 'numeric' => true],
                ['key' => 'completion', 'label' => 'نسبة الإنجاز', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }

    /**
     * The programmes in reach, or null for all of them.
     *
     * @return Collection<int, int>|null
     */
    private function stagesInReach(ReportQuery $query): ?Collection
    {
        if ($query->scope->reachesAll()) {
            return null;
        }

        return Circle::whereIn('id', $query->scope->circleIds() ?? collect())
            ->pluck('stage_id')
            ->unique()
            ->values();
    }

    private function rate(int $part, int $whole): string
    {
        return $whole > 0 ? round($part / $whole * 100).'%' : '—';
    }
}
