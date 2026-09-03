<?php

namespace App\Services\Reports;

use App\Models\GuardianNotification;
use App\Models\Student;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;
use Illuminate\Support\Collection;

/**
 * What reached the families, and what they read.
 *
 * A message sent is not a message received. The gap between the two is the
 * point of the report: a cohort with a hundred notices and ten readings has a
 * communication problem that no count of notices alone would show.
 */
class FamilyContactReport implements Report
{
    use GathersRows;
    use GroupsByStudent;

    private const SUMS = ['sent', 'read'];

    public function key(): string
    {
        return 'family-contact';
    }

    public function label(): string
    {
        return 'التواصل مع الأسرة';
    }

    public function description(): string
    {
        return 'ما أُرسل إلى أولياء الأمور في المدة، وكم منه قُرئ.';
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
            $row['read_rate'] = $this->rate($row['read'], $row['sent']);
            $row['silent'] = $row['sent'] === 0 ? 'نعم' : '—';
        }
        unset($row);

        $totals = $this->total($rows, self::SUMS);
        $totals['name'] = 'الإجمالي';
        $totals['read_rate'] = $this->rate($totals['read'], $totals['sent']);
        $totals['silent'] = '';

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'الاسم'],
                ['key' => 'students', 'label' => 'عدد الطلاب', 'numeric' => true],
                ['key' => 'sent', 'label' => 'أُرسل', 'numeric' => true],
                ['key' => 'read', 'label' => 'قُرئ', 'numeric' => true],
                ['key' => 'read_rate', 'label' => 'نسبة القراءة', 'numeric' => true],
                ['key' => 'silent', 'label' => 'لم يصله شيء', 'numeric' => true],
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

        return GuardianNotification::whereIn('student_id', $ids)
            ->whereBetween('created_at', [$query->from->toDateString().' 00:00:00', $query->to->toDateString().' 23:59:59'])
            ->selectRaw('student_id, count(*) as sent, sum(case when read_at is not null then 1 else 0 end) as read_count')
            ->groupBy('student_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->student_id => [
                'sent' => (int) $row->sent,
                'read' => (int) $row->read_count,
            ]])
            ->all();
    }
}
