<?php

namespace App\Services\Reports;

use App\Models\Attendance;
use App\Models\StudentPlanDay;
use App\Models\Teacher;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;

/**
 * How the teachers themselves are doing.
 *
 * The one report here whose subject is not the student. It measures what a
 * teacher does rather than what his students achieve — days he took the
 * register, days he heard recitation — because a cohort's results say as much
 * about the students as the teacher, and these two say only about him.
 *
 * A teacher is in reach when one of his cohorts is, so it obeys the same reach
 * as everything else without needing a rule of its own.
 */
class TeacherPerformanceReport implements Report
{
    use GroupsByStudent;

    public function key(): string
    {
        return 'teacher-performance';
    }

    public function label(): string
    {
        return 'أداء المعلمين';
    }

    public function description(): string
    {
        return 'أيام رصد الحضور، وأيام التسميع المقيَّم، وعدد الطلاب لكل معلم.';
    }

    public function run(ReportQuery $query): ReportResult
    {
        $circleIds = $query->scope->circleIds();

        $teachers = Teacher::query()
            ->with('circles')
            ->when($circleIds !== null, fn ($q) => $q->whereHas(
                'circles',
                fn ($c) => $c->whereIn('circles.id', $circleIds),
            ))
            ->orderBy('name')
            ->get();

        $from = $query->from->toDateString();
        $to = $query->to->toDateString();
        $ids = $teachers->pluck('id');

        $registerDays = $ids->isEmpty() ? collect() : Attendance::whereIn('teacher_id', $ids)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('teacher_id, count(distinct date) as days, count(*) as records')
            ->groupBy('teacher_id')
            ->get()
            ->keyBy('teacher_id');

        $recitationDays = $ids->isEmpty() ? collect() : StudentPlanDay::query()
            ->join('student_plans', 'student_plans.id', '=', 'student_plan_days.student_plan_id')
            ->whereIn('student_plans.teacher_id', $ids)
            ->where(fn ($q) => $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement'))
            ->whereBetween('student_plan_days.date', [$from, $to])
            ->selectRaw('student_plans.teacher_id as tid, count(distinct student_plan_days.date) as days, count(*) as gradings')
            ->groupBy('student_plans.teacher_id')
            ->get()
            ->keyBy('tid');

        $rows = [];

        foreach ($teachers as $teacher) {
            $register = $registerDays->get($teacher->id);
            $recitation = $recitationDays->get($teacher->id);

            $rows[] = [
                'name' => $teacher->name,
                'circles' => $teacher->circles->count(),
                'students' => $teacher->circles->sum(fn ($circle) => $circle->students()->count()),
                'register_days' => (int) ($register->days ?? 0),
                'recitation_days' => (int) ($recitation->days ?? 0),
                'gradings' => (int) ($recitation->gradings ?? 0),
            ];
        }

        $totals = ['name' => 'الإجمالي'];

        foreach (['circles', 'students', 'register_days', 'recitation_days', 'gradings'] as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'المعلم'],
                ['key' => 'circles', 'label' => 'دفعاته', 'numeric' => true],
                ['key' => 'students', 'label' => 'طلابه', 'numeric' => true],
                ['key' => 'register_days', 'label' => 'أيام رصد الحضور', 'numeric' => true],
                ['key' => 'recitation_days', 'label' => 'أيام تسميع مقيَّم', 'numeric' => true],
                ['key' => 'gradings', 'label' => 'عدد التقييمات', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }
}
