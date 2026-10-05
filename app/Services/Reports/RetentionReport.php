<?php

namespace App\Services\Reports;

use App\Models\Student;
use App\Models\StudentStatusHistory;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;
use App\Support\StudentStatus;
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
     * Each student's run through the period, read from where he stood when it
     * opened to where he stood when it closed.
     *
     * Three readings used to go wrong. Only changes inside the period were read,
     * so a student away since before it, and still away, had none and stood
     * «على انتظامه». Any status before مشارك counted as an absence, so a new
     * student placed from تحت التسجيل «returned». And a return scheduled for a
     * day still to come counted as one already made.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array<string, int>>
     */
    private function measureAll($students, ReportQuery $query): array
    {
        $ids = $students->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $from = $query->from->toDateString();
        $until = min($query->to->toDateString(), now('Asia/Riyadh')->toDateString());

        $histories = StudentStatusHistory::whereIn('student_id', $ids)
            ->whereDate('start_date', '<=', $until)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id');

        $measures = [];

        foreach ($students as $student) {
            $periods = $histories->get($student->id) ?? collect();
            $before = $periods->filter(fn (StudentStatusHistory $period) => $period->start_date->toDateString() < $from)->last();
            $within = $periods->filter(fn (StudentStatusHistory $period) => $period->start_date->toDateString() >= $from)->values();

            $away = $before !== null && (bool) StudentStatus::of($before->status)?->isAway();
            $returned = false;

            foreach ($within as $period) {
                $status = StudentStatus::of($period->status);

                if ($status?->isAway()) {
                    $away = true;
                } elseif ($status === StudentStatus::Active && $away) {
                    $returned = true;
                    $away = false;
                }
            }

            // Where he stood when the period closed decides the last two counts;
            // returning after leaving is a return, not a departure.
            $endedAway = (bool) StudentStatus::of(($within->last() ?? $before)?->status ?? $student->status)?->isAway();

            $measures[$student->id] = [
                'active' => $endedAway ? 0 : 1,
                'left' => $endedAway ? 1 : 0,
                'returned' => $returned ? 1 : 0,
                'changes' => $within->count(),
            ];
        }

        return $measures;
    }
}
