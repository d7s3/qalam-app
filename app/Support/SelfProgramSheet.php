<?php

namespace App\Support;

use App\Models\SelfProgramTrack;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * The year's programme as a supervisor hands it over: one row per week per
 * field.
 *
 * A wide sheet — five fields spread across eleven columns — is quicker to fill
 * but unreadable once a mistake creeps in, and it cannot say which of the eleven
 * a bad cell sits in. One row per field keeps every error addressable: "row 14,
 * the amount".
 *
 * Fields are named in Arabic, as the supervisor knows them, rather than by the
 * enum's English keys; the keys are accepted too, for a sheet the system itself
 * produced.
 */
class SelfProgramSheet
{
    /** @var array<int, string> */
    public const COLUMNS = ['الأسبوع', 'المجال', 'المحتوى', 'المقدار', 'الوحدة'];

    /**
     * Read a sheet into rows, keeping each row's own number so a complaint about
     * it can name where to look.
     *
     * `$extension` says which format to read it as; without one the path is
     * asked, which only answers for files stored under their own name.
     *
     * @return array{rows: array<int, array{line: int, week: int, track: SelfProgramTrack, description: ?string, amount: float, unit: ?string}>, errors: array<int, string>}
     */
    public static function read(string $path, ?string $extension = null): array
    {
        $extension ??= pathinfo($path, PATHINFO_EXTENSION);

        // The academic calendar is imported from a screen away, and its sheet
        // has no week column at all — so every one of its rows would be refused
        // for a missing week number, which says nothing about the real mistake.
        if (AcademicCalendarSheet::isCalendarGrid($path, $extension)) {
            return [
                'rows' => [],
                'errors' => ['هذا الملف تقويمٌ أكاديمي لا برنامجاً ذاتياً؛ استورده من شاشة «التقويم الأكاديمي».'],
            ];
        }

        $reader = self::readerFor($extension);
        $reader->open($path);

        $rows = [];
        $errors = [];
        $line = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map(
                        static fn ($cell) => is_string($cell) ? trim($cell) : $cell,
                        $row->toArray(),
                    );

                    // The header, however it is spelled, and any blank spacer row.
                    if ($line === 1 || self::isBlank($cells)) {
                        continue;
                    }

                    $parsed = self::parseRow($cells, $line);

                    if (is_string($parsed)) {
                        $errors[] = $parsed;

                        continue;
                    }

                    $rows[] = $parsed;
                }

                // Only the first sheet is the programme; later ones are the
                // supervisor's own notes.
                break;
            }
        } finally {
            // A corrupt workbook throws mid-iteration; the handle is released
            // either way.
            $reader->close();
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array{line: int, week: int, track: SelfProgramTrack, description: ?string, amount: float, unit: ?string}|string
     */
    private static function parseRow(array $cells, int $line): array|string
    {
        $week = (int) ($cells[0] ?? 0);

        if ($week < 1) {
            return "السطر {$line}: رقم الأسبوع غير صالح.";
        }

        $track = self::trackFrom((string) ($cells[1] ?? ''));

        if (! $track) {
            return "السطر {$line}: المجال \"{$cells[1]}\" غير معروف.";
        }

        $written = trim((string) ($cells[4] ?? ''));

        // A sheet says what it means, so a unit the field does not take is
        // refused by name rather than quietly swapped for one that fits.
        if ($written !== '' && ! $track->allowsUnit($written)) {
            $allowed = implode(' أو ', array_keys($track->unitOptions()));

            return "السطر {$line}: {$track->label()} لا يُقاس بـ\"{$written}\" — بل بـ{$allowed}.";
        }

        $unit = $track->unitFor($written ?: null);
        $amount = self::amountFrom($cells[3] ?? 0, $unit);

        if ($amount === null) {
            return SelfProgramUnit::isDuration($unit)
                ? "السطر {$line}: المقدار وقتٌ، فاكتبه دقائقَ أو هكذا 1:30."
                : "السطر {$line}: المقدار يجب أن يكون رقماً.";
        }

        return [
            'line' => $line,
            'week' => $week,
            'track' => $track,
            'description' => ($cells[2] ?? '') !== '' ? (string) $cells[2] : null,
            'amount' => $amount,
            'unit' => $unit,
        ];
    }

    /**
     * Read the amount a cell holds, in whatever the field is measured in.
     *
     * Time is written the way a person writes it — `1:30`, or plainly in
     * minutes — and kept in minutes. Everything else is a count, and is made
     * whole. `null` means the cell was not a quantity at all.
     */
    private static function amountFrom(mixed $written, string $unit): ?float
    {
        $written = trim((string) $written);

        if ($written === '') {
            return 0.0;
        }

        if (SelfProgramUnit::isDuration($unit) && preg_match('/^(\d{1,2})\s*:\s*(\d{1,2})$/', $written, $found)) {
            return SelfProgramUnit::fromHoursAndMinutes($found[1], $found[2]);
        }

        if (! is_numeric($written)) {
            return null;
        }

        return SelfProgramUnit::normalise((float) $written, $unit);
    }

    /**
     * Match a field by the name a supervisor writes, or by the key the system
     * itself uses.
     */
    public static function trackFrom(string $value): ?SelfProgramTrack
    {
        $value = trim($value);

        foreach (SelfProgramTrack::ordered() as $track) {
            if ($value === $track->value || $value === $track->label()) {
                return $track;
            }
        }

        return null;
    }

    /** @param  array<int, mixed>  $cells */
    private static function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Chosen from the name the file was uploaded under, not from where it is
     * stored: a temporary upload sits under a generated path that carries no
     * extension at all, and guessing from that reads every CSV as a workbook.
     */
    private static function readerFor(string $extension): ReaderInterface
    {
        return in_array(strtolower(ltrim($extension, '.')), ['csv', 'txt'], true)
            ? new CsvReader
            : new XlsxReader;
    }

    /**
     * A blank sheet in the shape the reader expects, with a filled first row to
     * show what belongs where.
     *
     * Written as CSV with a UTF-8 byte-order mark, which is how this application
     * already hands spreadsheets to Excel so Arabic survives the trip.
     */
    public static function template(int $weeks = 4): string
    {
        $out = "\u{FEFF}".implode(',', self::COLUMNS)."\n";
        $tracks = SelfProgramTrack::ordered();

        for ($week = 1; $week <= $weeks; $week++) {
            foreach ($tracks as $track) {
                $unit = $track->defaultUnit();

                // A field measured in time shows the shape its cell takes, so
                // nobody has to guess whether to write hours or minutes.
                $out .= implode(',', [
                    $week,
                    $track->label(),
                    '',
                    SelfProgramUnit::isDuration($unit) ? '0:00' : 0,
                    $unit,
                ])."\n";
            }
        }

        return $out;
    }
}
