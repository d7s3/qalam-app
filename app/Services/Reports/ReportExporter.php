<?php

namespace App\Services\Reports;

use App\Support\HijriDate;
use Illuminate\Support\Str;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handing a report over as a file.
 *
 * One writer and one printed sheet for every report there will ever be, because
 * every report answers in the same three parts. A report that grew its own pair
 * would have to keep them in step with the table on screen, and they would drift.
 */
class ReportExporter
{
    /**
     * As a spreadsheet.
     *
     * Written as CSV with a UTF-8 byte-order mark, which is how this application
     * already hands tables to Excel so the Arabic survives the trip.
     */
    public static function csv(ReportResult $result): StreamedResponse
    {
        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\u{FEFF}");
            fputcsv($out, [$result->title]);

            if ($result->subtitle) {
                fputcsv($out, [$result->subtitle]);
            }

            fputcsv($out, []);
            fputcsv($out, $result->headings());

            foreach ($result->rows as $row) {
                fputcsv($out, $result->cells($row));
            }

            if ($result->totals !== []) {
                fputcsv($out, $result->cells($result->totals));
            }

            fclose($out);
        }, self::filename($result, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * As a sheet to print or hand over.
     */
    public static function pdf(ReportResult $result)
    {
        // The same settings the plan sheets are printed with, which are the
        // ones known to render this application's Arabic correctly.
        $pdf = LaravelMpdf::loadView('pdf.report', ['result' => $result], [], [
            // Landscape: a report is wider than a plan, and a column pushed off
            // the page is a column nobody reads.
            'format' => 'A4-L',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
            'useAdobeCJK' => true,
        ]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            self::filename($result, 'pdf'),
            ['Content-Type' => 'application/pdf'],
        );
    }

    private static function filename(ReportResult $result, string $extension): string
    {
        // The Hijri date is what the academy files by, and a slug keeps the
        // name safe on every system it may be saved to.
        $stamp = HijriDate::gregorian(now());
        $name = Str::slug($result->title, '-', null) ?: 'report';

        return "{$name}-{$stamp}.{$extension}";
    }
}
