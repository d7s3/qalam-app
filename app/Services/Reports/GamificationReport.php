<?php

namespace App\Services\Reports;

use App\Models\GamificationTransaction;
use App\Models\Student;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;
use Illuminate\Support\Collection;

/**
 * What students earned and spent in the competitions.
 *
 * Earnings and spending are separated rather than netted: a student who earned
 * a great deal and spent it all reads the same as one who earned nothing, and
 * the two are not the same student.
 */
class GamificationReport implements Report
{
    use GathersRows;
    use GroupsByStudent;

    private const SUMS = ['xp', 'earned', 'spent', 'entries'];

    public function key(): string
    {
        return 'gamification';
    }

    public function label(): string
    {
        return 'التحفيز';
    }

    public function description(): string
    {
        return 'طاقة الخبرة المكتسبة، والعملات المكتسبة والمنفَقة، وعدد ما استُحق في المدة.';
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
            $row['balance'] = $row['earned'] - $row['spent'];
            $row['xp_each'] = $row['students'] > 0 ? round($row['xp'] / $row['students'], 1) : '—';
        }
        unset($row);

        $totals = $this->total($rows, self::SUMS);
        $totals['name'] = 'الإجمالي';
        $totals['balance'] = $totals['earned'] - $totals['spent'];
        $totals['xp_each'] = $totals['students'] > 0 ? round($totals['xp'] / $totals['students'], 1) : '—';

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'الاسم'],
                ['key' => 'students', 'label' => 'عدد الطلاب', 'numeric' => true],
                ['key' => 'xp', 'label' => 'طاقة الخبرة', 'numeric' => true],
                ['key' => 'xp_each', 'label' => 'متوسط الفرد', 'numeric' => true],
                ['key' => 'earned', 'label' => 'عملات مكتسبة', 'numeric' => true],
                ['key' => 'spent', 'label' => 'عملات منفَقة', 'numeric' => true],
                ['key' => 'balance', 'label' => 'الصافي', 'numeric' => true],
                ['key' => 'entries', 'label' => 'عدد الحركات', 'numeric' => true],
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

        return GamificationTransaction::whereIn('student_id', $ids)
            ->whereBetween('created_at', [$query->from->toDateString().' 00:00:00', $query->to->toDateString().' 23:59:59'])
            ->selectRaw("student_id, count(*) as entries, sum(xp_amount) as xp,
                sum(case when type = 'earn' then amount else 0 end) as earned,
                sum(case when type != 'earn' then amount else 0 end) as spent")
            ->groupBy('student_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->student_id => [
                'entries' => (int) $row->entries,
                'xp' => (int) $row->xp,
                'earned' => (int) $row->earned,
                'spent' => (int) $row->spent,
            ]])
            ->all();
    }
}
