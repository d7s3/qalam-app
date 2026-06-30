<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormResponsesExporter
{
    /**
     * Build the export grid (header + one row per response).
     *
     * The student linkage columns are supervisor-only; they are omitted from the
     * public report export to avoid leaking student data.
     *
     * @param  Collection<int, FormResponse>  $responses
     * @return array<int, array<int, string>>
     */
    public static function rows(Form $form, Collection $responses, bool $includeStudent = false): array
    {
        $fields = collect($form->fields);

        $header = ['تاريخ الرد'];
        foreach ($fields as $field) {
            $header[] = $field['label'];
        }
        if ($includeStudent) {
            $header[] = 'الحالة';
            $header[] = 'الطالب المرتبط';
        }

        $rows = [$header];

        foreach ($responses as $response) {
            $row = [$response->created_at->format('Y-m-d H:i')];
            foreach ($fields as $field) {
                $answer = $response->answers[$field['id']] ?? '';
                $row[] = is_array($answer) ? implode('، ', $answer) : (string) $answer;
            }
            if ($includeStudent) {
                $row[] = $response->student_id ? 'مرتبط' : 'غير معالج';
                $row[] = $response->student?->name ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Stream the rows as an Excel-compatible CSV (UTF-8 BOM so Arabic renders).
     *
     * @param  array<int, array<int, string>>  $rows
     */
    public static function stream(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Arabic correctly.
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
