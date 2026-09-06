<?php

namespace App\Services\Reports\Concerns;

use App\Models\Student;
use App\Services\Reports\ReportQuery;
use Illuminate\Support\Collection;

/**
 * Rolling per-student figures up into whatever the reader asked to see.
 *
 * Every report gathers the same way — by student, by cohort, by programme, or
 * the whole reach in one row — and only the numbers being added differ. So the
 * gathering lives here once rather than three times over.
 */
trait GathersRows
{
    /**
     * @param  Collection<int, Student>  $students
     * @param  callable(Student): array<string, float|int>  $measure
     * @param  array<int, string>  $sums  keys added together
     * @return array<int, array<string, mixed>>
     */
    protected function gather(
        ReportQuery $query,
        Collection $students,
        callable $measure,
        array $sums,
    ): array {
        $groups = [];

        foreach ($students as $student) {
            $label = $query->groupLabelFor($student);
            $figures = $measure($student);

            if (! isset($groups[$label])) {
                $groups[$label] = array_merge(
                    ['name' => $label, 'students' => 0],
                    array_fill_keys($sums, 0),
                );
            }

            $groups[$label]['students']++;

            foreach ($sums as $key) {
                $groups[$label][$key] += $figures[$key] ?? 0;
            }
        }

        return array_values($groups);
    }

    /**
     * Add a set of rows up into one, for the total line under the table.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $sums
     * @return array<string, mixed>
     */
    protected function total(array $rows, array $sums): array
    {
        $totals = array_fill_keys($sums, 0);
        $totals['students'] = 0;

        foreach ($rows as $row) {
            $totals['students'] += $row['students'] ?? 0;

            foreach ($sums as $key) {
                $totals[$key] += $row[$key] ?? 0;
            }
        }

        return $totals;
    }

    /**
     * A percentage that reads as a dash when there was nothing to divide by,
     * rather than as a zero that looks like a failure.
     */
    protected function rate(float|int $part, float|int $whole): string
    {
        return $whole > 0 ? round($part / $whole * 100).'%' : '—';
    }
}
