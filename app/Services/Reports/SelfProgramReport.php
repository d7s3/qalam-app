<?php

namespace App\Services\Reports;

use App\Models\SelfProgramWeek;
use App\Models\Student;
use App\Models\StudentSelfProgramEntry;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Services\SelfProgramService;
use App\Support\HijriDate;
use App\Support\SelfProgramTrack;
use Illuminate\Support\Collection;

/**
 * How far students got through the self programme over a period.
 *
 * Read across every week the period touches rather than the current one, so the
 * report answers "how has he been doing" and not only "how is he doing today".
 *
 * The whole set of students is measured in a handful of queries: a report reads
 * about a group by its nature, and asking per student is what made the progress
 * screen run twelve hundred queries for a programme of two hundred.
 */
class SelfProgramReport implements Report
{
    use GathersRows;
    use GroupsByStudent;

    private const SUMS = ['weeks', 'percent_sum', 'complete', 'unlocked', 'arrears'];

    public function key(): string
    {
        return 'self-program';
    }

    public function label(): string
    {
        return 'البرنامج الذاتي';
    }

    public function description(): string
    {
        return 'متوسط إنجاز المجالات الخمسة، والأسابيع المكتملة، والمتأخرات، وبلوغ الإثرائي.';
    }

    public function run(ReportQuery $query): ReportResult
    {
        $students = $query->students();
        $measures = $this->measureAll($students, $query);

        $rows = $this->gather(
            $query,
            $students,
            fn (Student $student) => $measures[$student->id] ?? [],
            self::SUMS,
        );

        foreach ($rows as &$row) {
            $row['average'] = $row['weeks'] > 0
                ? round($row['percent_sum'] / $row['weeks'], 1).'%'
                : '—';
        }
        unset($row);

        $totals = $this->total($rows, self::SUMS);
        $totals['name'] = 'الإجمالي';
        $totals['average'] = $totals['weeks'] > 0
            ? round($totals['percent_sum'] / $totals['weeks'], 1).'%'
            : '—';

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'الاسم'],
                ['key' => 'students', 'label' => 'عدد الطلاب', 'numeric' => true],
                ['key' => 'weeks', 'label' => 'أسابيع في المدة', 'numeric' => true],
                ['key' => 'average', 'label' => 'متوسط الإنجاز', 'numeric' => true],
                ['key' => 'complete', 'label' => 'أسابيع مكتملة', 'numeric' => true],
                ['key' => 'unlocked', 'label' => 'بلغ الإثرائي', 'numeric' => true],
                ['key' => 'arrears', 'label' => 'بنود متأخرة', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }

    /**
     * Every student's figures, in four queries however many students there are.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array<string, float|int>>
     */
    private function measureAll($students, ReportQuery $query): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        $stageIds = $students->map(fn (Student $s) => $s->effective_stage_id)->filter()->unique();

        // Every week of the programme the period touches, for those programmes.
        $weeks = SelfProgramWeek::self()
            ->whereIn('stage_id', $stageIds)
            ->whereDate('starts_on', '<=', $query->to->toDateString())
            ->whereDate('ends_on', '>=', $query->from->toDateString())
            ->with('items')
            ->get()
            ->groupBy('stage_id');

        $itemIds = $weeks->flatten(1)->pluck('items')->flatten()->pluck('id');

        $totals = [];

        if ($itemIds->isNotEmpty()) {
            StudentSelfProgramEntry::whereIn('student_id', $students->pluck('id'))
                ->whereIn('self_program_item_id', $itemIds)
                ->selectRaw('student_id, self_program_item_id, sum(amount_done) as total')
                ->groupBy('student_id', 'self_program_item_id')
                ->get()
                ->each(function ($row) use (&$totals) {
                    $totals[$row->student_id][$row->self_program_item_id] = (float) $row->total;
                });
        }

        $measures = [];

        foreach ($students as $student) {
            $mine = $totals[$student->id] ?? [];
            $figures = ['weeks' => 0, 'percent_sum' => 0.0, 'complete' => 0, 'unlocked' => 0, 'arrears' => 0];

            foreach ($weeks->get($student->effective_stage_id) ?? collect() as $week) {
                $percentages = [];

                foreach ($week->items as $item) {
                    $target = (float) $item->target_amount;

                    if ($target <= 0) {
                        continue;
                    }

                    $done = (float) ($mine[$item->id] ?? 0);
                    $percentages[] = min(100.0, $done / $target * 100);

                    if ($done < $target && $week->ends_on->lt($query->to)) {
                        $figures['arrears']++;
                    }
                }

                if ($percentages === []) {
                    continue;
                }

                $overall = array_sum($percentages) / count($percentages);

                $figures['weeks']++;
                $figures['percent_sum'] += $overall;
                $figures['complete'] += $overall >= 100.0 ? 1 : 0;
                $figures['unlocked'] += $overall >= SelfProgramService::ENRICHMENT_THRESHOLD ? 1 : 0;
            }

            $measures[$student->id] = $figures;
        }

        return $measures;
    }

    /** @return array<int, SelfProgramTrack> */
    public static function tracks(): array
    {
        return SelfProgramTrack::ordered();
    }
}
