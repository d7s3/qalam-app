<?php

namespace App\Services\Reports;

use App\Models\Student;
use App\Models\StudentHadithAchievement;
use App\Models\StudentOdeAchievement;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;
use Illuminate\Support\Collection;

/**
 * What was memorised of the texts and the didactic poems.
 *
 * Counted by graded days rather than by lines: a day of the path is what the
 * teacher actually judges, and the lines behind it vary from one text to the
 * next so adding them would compare things that are not alike.
 */
class MutunReport implements Report
{
    use GathersRows;
    use GroupsByStudent;

    private const SUMS = ['hadith_days', 'hadith_excellent', 'ode_days', 'ode_excellent'];

    public function key(): string
    {
        return 'mutun';
    }

    public function label(): string
    {
        return 'المتون والمنظومات';
    }

    public function description(): string
    {
        return 'أيام حفظ المتون والمنظومات المقيَّمة في المدة، وما نال منها تقدير الإتقان.';
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

        $totals = $this->total($rows, self::SUMS);
        $totals['name'] = 'الإجمالي';

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'الاسم'],
                ['key' => 'students', 'label' => 'عدد الطلاب', 'numeric' => true],
                ['key' => 'hadith_days', 'label' => 'أيام المتون', 'numeric' => true],
                ['key' => 'hadith_excellent', 'label' => 'إتقان المتون', 'numeric' => true],
                ['key' => 'ode_days', 'label' => 'أيام المنظومات', 'numeric' => true],
                ['key' => 'ode_excellent', 'label' => 'إتقان المنظومات', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }

    /**
     * Both kinds counted in two queries, whatever the number of students.
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
        $to = $query->to->toDateString();

        $hadith = StudentHadithAchievement::query()
            ->join('student_hadith_plans', 'student_hadith_plans.id', '=', 'student_hadith_achievements.student_hadith_plan_id')
            ->whereIn('student_hadith_plans.student_id', $ids)
            ->whereNotNull('hifz_achievement')
            ->whereBetween('student_hadith_achievements.hifz_graded_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('student_hadith_plans.student_id as sid, count(*) as days, sum(case when hifz_achievement = 3 then 1 else 0 end) as excellent')
            ->groupBy('student_hadith_plans.student_id')
            ->get();

        $ode = StudentOdeAchievement::query()
            ->join('student_ode_plans', 'student_ode_plans.id', '=', 'student_ode_achievements.student_ode_plan_id')
            ->whereIn('student_ode_plans.student_id', $ids)
            ->whereNotNull('hifz_achievement')
            ->whereBetween('student_ode_achievements.hifz_graded_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('student_ode_plans.student_id as sid, count(*) as days, sum(case when hifz_achievement = 3 then 1 else 0 end) as excellent')
            ->groupBy('student_ode_plans.student_id')
            ->get();

        $measures = [];

        foreach ($hadith as $row) {
            $measures[$row->sid]['hadith_days'] = (int) $row->days;
            $measures[$row->sid]['hadith_excellent'] = (int) $row->excellent;
        }

        foreach ($ode as $row) {
            $measures[$row->sid]['ode_days'] = (int) $row->days;
            $measures[$row->sid]['ode_excellent'] = (int) $row->excellent;
        }

        return $measures;
    }
}
