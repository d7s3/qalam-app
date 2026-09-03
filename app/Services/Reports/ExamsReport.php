<?php

namespace App\Services\Reports;

use App\Models\Student;
use App\Models\StudentExam;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;
use Illuminate\Support\Collection;

/**
 * How students fared in their examinations.
 *
 * Only sat examinations are weighed. One that is scheduled and not yet held
 * would otherwise read as a nought, and a student would appear to have failed
 * an examination he has not taken.
 */
class ExamsReport implements Report
{
    use GathersRows;
    use GroupsByStudent;

    private const PASS_MARK = 60;

    private const SUMS = ['sat', 'passed', 'score_sum'];

    public function key(): string
    {
        return 'exams';
    }

    public function label(): string
    {
        return 'الاختبارات';
    }

    public function description(): string
    {
        return 'ما أُدّي من الاختبارات في المدة، ومتوسط الدرجات، ونسبة النجاح.';
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
            $row['average'] = $row['sat'] > 0 ? round($row['score_sum'] / $row['sat'], 1).'%' : '—';
            $row['pass_rate'] = $this->rate($row['passed'], $row['sat']);
        }
        unset($row);

        $totals = $this->total($rows, self::SUMS);
        $totals['name'] = 'الإجمالي';
        $totals['average'] = $totals['sat'] > 0 ? round($totals['score_sum'] / $totals['sat'], 1).'%' : '—';
        $totals['pass_rate'] = $this->rate($totals['passed'], $totals['sat']);

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to)
                .' — النجاح من '.self::PASS_MARK.'%',
            columns: [
                ['key' => 'name', 'label' => 'الاسم'],
                ['key' => 'students', 'label' => 'عدد الطلاب', 'numeric' => true],
                ['key' => 'sat', 'label' => 'اختبارات أُدّيت', 'numeric' => true],
                ['key' => 'average', 'label' => 'متوسط الدرجة', 'numeric' => true],
                ['key' => 'passed', 'label' => 'ناجحة', 'numeric' => true],
                ['key' => 'pass_rate', 'label' => 'نسبة النجاح', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array<string, float|int>>
     */
    private function measureAll($students, ReportQuery $query): array
    {
        $ids = $students->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return StudentExam::whereIn('student_id', $ids)
            ->whereNotNull('score_percentage')
            ->whereBetween('date_time', [$query->from->toDateString().' 00:00:00', $query->to->toDateString().' 23:59:59'])
            ->selectRaw('student_id, count(*) as sat, sum(score_percentage) as score_sum, sum(case when score_percentage >= ? then 1 else 0 end) as passed', [self::PASS_MARK])
            ->groupBy('student_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->student_id => [
                'sat' => (int) $row->sat,
                'passed' => (int) $row->passed,
                'score_sum' => (float) $row->score_sum,
            ]])
            ->all();
    }
}
