<?php

namespace App\Services\Reports;

use App\Models\Student;
use App\Models\StudentStatusHistory;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;
use Illuminate\Support\Collection;

/**
 * Who stayed, who left, and who came back.
 *
 * Read from the record of status changes rather than from the status a student
 * holds today: today's status says where he ended up, and the question here is
 * what happened along the way. A student who left and returned reads as steady
 * by his status alone, and that is precisely the case worth seeing.
 */
class RetentionReport implements Report
{
    use GathersRows;
    use GroupsByStudent;

    private const SUMS = ['active', 'left', 'returned', 'changes'];

    public function key(): string
    {
        return 'retention';
    }

    public function label(): string
    {
        return 'الانتظام والتسرّب';
    }

    public function description(): string
    {
        return 'من بقي على انتظامه، ومن انقطع، ومن عاد بعد انقطاع، خلال المدة.';
    }

    public function run(ReportQuery $query): ReportResult
    {
        $students = $query->students();
        $measures = $this->measureAll($students, $query);

        $rows = $this->gather(
            $query,
            $students,
            fn (Student $student) => $measures[$student->id] ?? ['active' => 1, 'left' => 0, 'returned' => 0, 'changes' => 0],
            self::SUMS,
        );

        foreach ($rows as &$row) {
            $row['retention'] = $this->rate($row['students'] - $row['left'], $row['students']);
        }
        unset($row);

        $totals = $this->total($rows, self::SUMS);
        $totals['name'] = 'الإجمالي';
        $totals['retention'] = $this->rate($totals['students'] - $totals['left'], $totals['students']);

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'الاسم'],
                ['key' => 'students', 'label' => 'عدد الطلاب', 'numeric' => true],
                ['key' => 'active', 'label' => 'على انتظامه', 'numeric' => true],
                ['key' => 'left', 'label' => 'انقطع', 'numeric' => true],
                ['key' => 'returned', 'label' => 'عاد بعد انقطاع', 'numeric' => true],
                ['key' => 'retention', 'label' => 'نسبة البقاء', 'numeric' => true],
                ['key' => 'changes', 'label' => 'تحوّلات الحالة', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array<string, int>>
     */
    private function measureAll($students, ReportQuery $query): array
    {
        $ids = $students->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $histories = StudentStatusHistory::whereIn('student_id', $ids)
            ->whereBetween('start_date', [$query->from->toDateString(), $query->to->toDateString()])
            ->orderBy('start_date')
            ->get()
            ->groupBy('student_id');

        $measures = [];

        foreach ($students as $student) {
            $rows = $histories->get($student->id) ?? collect();
            $statuses = $rows->pluck('status')->all();

            $everLeft = false;
            $returned = 0;

            foreach ($statuses as $status) {
                $isActive = $status === 'active';

                if (! $isActive) {
                    $everLeft = true;
                } elseif ($everLeft) {
                    // Active again after having been anything else.
                    $returned++;
                }
            }

            // Where he stood when the period closed decides the last two counts;
            // returning after leaving is a return, not a departure.
            $endedAway = $statuses !== [] && end($statuses) !== 'active';

            $measures[$student->id] = [
                'active' => $endedAway ? 0 : 1,
                'left' => $endedAway ? 1 : 0,
                'returned' => $returned > 0 ? 1 : 0,
                'changes' => count($statuses),
            ];
        }

        return $measures;
    }
}
