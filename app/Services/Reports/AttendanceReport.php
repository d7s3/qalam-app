<?php

namespace App\Services\Reports;

use App\Services\CircleReportService;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;

/**
 * Who attended, and against how many days they were expected to.
 *
 * The rate is measured against the working days the academic calendar names for
 * the student's own programme, not against the days in the period: a month with
 * a fortnight of holidays in it must not read as half attendance.
 */
class AttendanceReport implements Report
{
    use GathersRows;
    use GroupsByStudent;

    private const SUMS = ['present', 'late', 'absent', 'excused', 'expected'];

    public function key(): string
    {
        return 'attendance';
    }

    public function label(): string
    {
        return 'الحضور والانضباط';
    }

    public function description(): string
    {
        return 'الحضور والتأخر والغياب والأعذار، منسوبةً إلى أيام الدوام الفعلية.';
    }

    public function run(ReportQuery $query): ReportResult
    {
        $students = $query->students();
        $built = CircleReportService::build($students, $query->from, $query->to);
        // Each row carries the student itself, not an id column.
        $per = $built['perStudent']->keyBy(fn (array $row) => $row['student']->id);

        $rows = $this->gather($query, $students, function ($student) use ($per) {
            $figures = $per->get($student->id, []);

            return [
                'present' => (int) ($figures['present'] ?? 0),
                'late' => (int) ($figures['late'] ?? 0),
                'absent' => (int) ($figures['absent'] ?? 0),
                'excused' => (int) ($figures['excused'] ?? 0),
                'expected' => (int) ($figures['expected_days'] ?? 0),
            ];
        }, self::SUMS);

        foreach ($rows as &$row) {
            $row['rate'] = $this->rate($row['present'] + $row['late'], $row['expected']);
        }
        unset($row);

        $totals = $this->total($rows, self::SUMS);
        $totals['name'] = 'الإجمالي';
        $totals['rate'] = $this->rate($totals['present'] + $totals['late'], $totals['expected']);

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'الاسم'],
                ['key' => 'students', 'label' => 'عدد الطلاب', 'numeric' => true],
                ['key' => 'present', 'label' => 'حاضر', 'numeric' => true],
                ['key' => 'late', 'label' => 'متأخر', 'numeric' => true],
                ['key' => 'absent', 'label' => 'غائب', 'numeric' => true],
                ['key' => 'excused', 'label' => 'بعذر', 'numeric' => true],
                ['key' => 'expected', 'label' => 'أيام الدوام', 'numeric' => true],
                ['key' => 'rate', 'label' => 'نسبة الحضور', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }
}
