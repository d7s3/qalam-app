<?php

namespace App\Services\Reports;

use App\Services\CircleReportService;
use App\Services\Reports\Concerns\GathersRows;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;

/**
 * What was memorised and revised, in mushaf pages.
 *
 * Memorisation is counted by distinct pages — the extent of the mushaf reached,
 * since re-reciting a page already held is not another page memorised. Revision
 * is counted as a running total, because returning to the same pages is the
 * substance of it. The two are measured differently on purpose; see
 * `CircleReportService::pageCountsPerStudent()`.
 */
class MemorizationReport implements Report
{
    use GathersRows;
    use GroupsByStudent;

    private const SUMS = ['hifz_pages', 'hifz_days', 'review_pages', 'review_days', 'hadiths', 'verses'];

    public function key(): string
    {
        return 'memorization';
    }

    public function label(): string
    {
        return 'الحفظ والمراجعة';
    }

    public function description(): string
    {
        return 'صفحات الحفظ والمراجعة وأيامهما، ومعهما المحفوظ من المتون والمنظومات.';
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
                'hifz_pages' => (int) ($figures['hifz_pages'] ?? 0),
                'hifz_days' => (int) ($figures['hifz_days'] ?? 0),
                'review_pages' => (int) ($figures['review_pages'] ?? 0),
                'review_days' => (int) ($figures['review_days'] ?? 0),
                'hadiths' => (int) ($figures['hadiths'] ?? 0),
                'verses' => (int) ($figures['verses'] ?? 0),
            ];
        }, self::SUMS);

        foreach ($rows as &$row) {
            $row['hifz_per_day'] = $row['hifz_days'] > 0
                ? round($row['hifz_pages'] / $row['hifz_days'], 1)
                : '—';
        }
        unset($row);

        $totals = $this->total($rows, self::SUMS);
        $totals['name'] = 'الإجمالي';
        $totals['hifz_per_day'] = $totals['hifz_days'] > 0
            ? round($totals['hifz_pages'] / $totals['hifz_days'], 1)
            : '—';

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'الاسم'],
                ['key' => 'students', 'label' => 'عدد الطلاب', 'numeric' => true],
                ['key' => 'hifz_pages', 'label' => 'صفحات الحفظ', 'numeric' => true],
                ['key' => 'hifz_days', 'label' => 'أيام الحفظ', 'numeric' => true],
                ['key' => 'hifz_per_day', 'label' => 'معدل الحفظ اليومي', 'numeric' => true],
                ['key' => 'review_pages', 'label' => 'صفحات المراجعة', 'numeric' => true],
                ['key' => 'review_days', 'label' => 'أيام المراجعة', 'numeric' => true],
                ['key' => 'hadiths', 'label' => 'أحاديث', 'numeric' => true],
                ['key' => 'verses', 'label' => 'أبيات', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }
}
