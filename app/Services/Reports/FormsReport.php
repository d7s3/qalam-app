<?php

namespace App\Services\Reports;

use App\Models\Form;
use App\Models\FormResponse;
use App\Services\Reports\Concerns\GroupsByStudent;
use App\Support\HijriDate;

/**
 * How the forms are being answered.
 *
 * A form's worth is in what came back, so the count of responses is the figure
 * — and beside it, how many were dealt with, since a stack of unread answers is
 * a form that is collecting rather than working.
 */
class FormsReport implements Report
{
    use GroupsByStudent;

    public function key(): string
    {
        return 'forms';
    }

    public function label(): string
    {
        return 'النماذج';
    }

    public function description(): string
    {
        return 'ما ورد من استجابات لكل نموذج في المدة، وكم منها عولج.';
    }

    public function run(ReportQuery $query): ReportResult
    {
        $from = $query->from->toDateString();
        $to = $query->to->toDateString();

        // Only the responses of students in reach are counted; one belonging to
        // nobody in particular is counted for everyone, since it is not a
        // student's to withhold.
        $reachable = $query->students()->pluck('id');
        $everyone = $query->scope->reachesAll();

        $counts = FormResponse::query()
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when(! $everyone, fn ($q) => $q->where(
                fn ($sub) => $sub->whereIn('student_id', $reachable)->orWhereNull('student_id'),
            ))
            ->selectRaw('form_id, count(*) as responses, sum(case when is_processed = 1 then 1 else 0 end) as processed')
            ->groupBy('form_id')
            ->get()
            ->keyBy('form_id');

        $rows = [];

        foreach (Form::orderBy('id')->get() as $form) {
            $count = $counts->get($form->id);

            $rows[] = [
                'name' => $form->title ?? $form->name ?? ('نموذج '.$form->id),
                'responses' => (int) ($count->responses ?? 0),
                'processed' => (int) ($count->processed ?? 0),
                'pending' => (int) ($count->responses ?? 0) - (int) ($count->processed ?? 0),
            ];
        }

        $totals = ['name' => 'الإجمالي'];

        foreach (['responses', 'processed', 'pending'] as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        return new ReportResult(
            title: $this->label(),
            subtitle: 'من '.HijriDate::withGregorian($query->from).' إلى '.HijriDate::withGregorian($query->to),
            columns: [
                ['key' => 'name', 'label' => 'النموذج'],
                ['key' => 'responses', 'label' => 'استجابات', 'numeric' => true],
                ['key' => 'processed', 'label' => 'عولجت', 'numeric' => true],
                ['key' => 'pending', 'label' => 'بانتظار المعالجة', 'numeric' => true],
            ],
            rows: $rows,
            totals: $totals,
        );
    }
}
