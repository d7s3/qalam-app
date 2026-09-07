<?php

namespace App\Support;

use App\Models\AcademicCalendarEvent;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * The academy's year, read off the sheet it was planned on.
 *
 * A calendar is drawn in a spreadsheet long before anybody types it into an
 * application — terms, holidays, exam weeks, the trips. Retyping thirty rows
 * into a form is how a calendar comes to be half-entered and then trusted.
 *
 * One row is one entry, and the columns are named in the words the academy
 * uses. A row that cannot be read names itself and its line, and the rest are
 * still imported: a single bad date should not cost the other twenty-nine.
 */
class AcademicCalendarSheet
{
    /** The header the template carries, and the order the columns are read in. */
    public const COLUMNS = [
        'اسم الحدث',
        'من',
        'إلى',
        'النوع',
        'أيام الأسبوع',
        'البرامج',
        'الوصف',
        'الأثر التربوي',
    ];

    /** What the type column may say, and which of the two it means. */
    private const PERIOD_WORDS = ['فترة دوام', 'فترة', 'دوام', 'period'];

    /** Sunday first, matching how the calendar stores its weekdays. */
    private const WEEKDAYS = [
        'الأحد' => 1, 'الاثنين' => 2, 'الإثنين' => 2, 'الثلاثاء' => 3,
        'الأربعاء' => 4, 'الخميس' => 5, 'الجمعة' => 6, 'السبت' => 7,
    ];

    /**
     * The weekday names a calendar grid is headed with.
     *
     * @var array<string, int>
     */
    private const HEADER_DAYS = [
        'الأحد' => 1, 'الاثنين' => 2, 'الإثنين' => 2, 'الثلاثاء' => 3,
        'الأربعاء' => 4, 'الخميس' => 5, 'الجمعة' => 6, 'السبت' => 7,
    ];

    /**
     * Read a calendar, in whichever of the two shapes it was written.
     *
     * One is a row per entry, which is what the template offers. The other is
     * the shape an academy actually plans in: a grid of weeks, the days across
     * and the weeks down, each cell holding a Hijri day number and whatever
     * falls on it — and the month named once in a column of its own.
     *
     * The second is not a lesser form of the first. It is how the year is
     * thought about, so it is read as it is rather than asked to be retyped.
     *
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public static function read(string $path, ?string $extension = null): array
    {
        $lines = self::lines($path, $extension);

        return self::looksLikeGrid($lines)
            ? self::readGrid($lines)
            : self::readColumns($lines);
    }

    /**
     * Every row of the first sheet, as trimmed strings.
     *
     * @return array<int, array<int, mixed>>
     */
    private static function lines(string $path, ?string $extension): array
    {
        $reader = self::readerFor($extension ?? pathinfo($path, PATHINFO_EXTENSION));
        $reader->open($path);

        $lines = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $lines[] = array_map(
                        static fn ($cell) => is_string($cell) ? trim($cell) : $cell,
                        $row->toArray(),
                    );
                }

                // The first sheet is the calendar; anything after it is notes.
                break;
            }
        } finally {
            // A corrupt workbook throws mid-iteration; the handle is released
            // either way.
            $reader->close();
        }

        return $lines;
    }

    /** A grid announces itself by a row naming three weekdays or more. */
    private static function looksLikeGrid(array $lines): bool
    {
        foreach (array_slice($lines, 0, 6) as $cells) {
            $named = 0;

            foreach ($cells as $cell) {
                if (isset(self::HEADER_DAYS[trim((string) $cell)])) {
                    $named++;
                }
            }

            if ($named >= 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, mixed>>  $lines
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    private static function readColumns(array $lines): array
    {
        $rows = [];
        $errors = [];
        $line = 0;

        foreach ($lines as $cells) {
            $line++;

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

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Read the academy's own grid.
     *
     * The title carries the Hijri year, one row heads the columns with the days
     * of the week, and every row after it is a week: each day cell opens with
     * its Hijri day number and carries whatever falls on it underneath, one to
     * a line. A column of its own names the month, written once where it turns.
     *
     * The month advances by watching the day numbers fall back — a week
     * beginning at ٣٠ and ending at ٦ has crossed one — so a sheet that names
     * the month only at its turns still reads.
     *
     * @param  array<int, array<int, mixed>>  $lines
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    private static function readGrid(array $lines): array
    {
        $year = null;
        $columns = [];
        $month = null;
        $rows = [];
        $errors = [];
        $lastDay = 0;

        foreach ($lines as $index => $cells) {
            $line = $index + 1;

            // The year, from wherever in the title it was written.
            if ($year === null) {
                foreach ($cells as $cell) {
                    if (preg_match('/(\d{4})/', HijriDate::digits((string) $cell), $found)) {
                        $year = (int) $found[1];

                        break;
                    }
                }
            }

            // The heading, which says which column is which day.
            if ($columns === []) {
                foreach ($cells as $column => $cell) {
                    $name = trim((string) $cell);

                    if (isset(self::HEADER_DAYS[$name])) {
                        $columns[$column] = self::HEADER_DAYS[$name];
                    }
                }

                if ($columns !== []) {
                    continue;
                }
            }

            if ($columns === [] || self::isBlank($cells)) {
                continue;
            }

            // The month column: named once, at its turn.
            foreach ($cells as $column => $cell) {
                if (isset($columns[$column])) {
                    continue;
                }

                if ($named = HijriDate::monthNumber((string) $cell)) {
                    $month = $named;
                    $lastDay = 0;
                }
            }

            if ($month === null || $year === null) {
                continue;
            }

            // Read in the week's own order, not the sheet's. A right-to-left
            // grid puts Saturday in the first column and Sunday in the last, so
            // walking the columns walks the week backwards — and every cell
            // then looks like the month turning under it.
            $inOrder = $columns;
            asort($inOrder);

            foreach ($inOrder as $column => $weekday) {
                $cell = trim((string) ($cells[$column] ?? ''));

                if ($cell === '') {
                    continue;
                }

                $parts = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $cell))));
                $day = (int) HijriDate::digits((string) array_shift($parts));

                if ($day < 1 || $day > 30) {
                    continue;
                }

                // A day number smaller than the one before it means the month
                // turned inside this week, which is the ordinary case.
                if ($day < $lastDay) {
                    $month = $month === 12 ? 1 : $month + 1;

                    if ($month === 1) {
                        $year++;
                    }
                }

                $lastDay = $day;

                $on = HijriDate::toGregorian($year, $month, $day);

                if (! $on) {
                    $errors[] = "السطر {$line}: لا يوجد يوم {$day} في هذا الشهر.";

                    continue;
                }

                foreach ($parts as $name) {
                    $rows[] = [
                        'line' => $line,
                        'event_name' => $name,
                        'start_date' => $on->format('Y-m-d'),
                        'end_date' => $on->format('Y-m-d'),
                        'is_attendance_period' => false,
                        'weekdays' => [],
                        'stage_names' => [],
                        'description' => null,
                        'formative_note' => null,
                    ];
                }
            }
        }

        if ($year === null) {
            $errors[] = 'لم أجد السنة الهجرية في عنوان الورقة — اكتبها هكذا: تقويم الفصل الأول ١٤٤٨ هـ.';
        }

        if ($columns === []) {
            $errors[] = 'لم أجد صفّاً يسمّي أيام الأسبوع.';
        }

        return ['rows' => self::joinRuns($rows), 'errors' => $errors];
    }

    /**
     * Join a name repeated on consecutive days into one entry that spans them.
     *
     * A holiday written on two days is one holiday, and the calendar reads a
     * span rather than two entries that happen to touch.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function joinRuns(array $rows): array
    {
        $byName = [];

        foreach ($rows as $row) {
            $byName[$row['event_name']][] = $row;
        }

        $joined = [];

        foreach ($byName as $entries) {
            usort($entries, fn ($a, $b) => $a['start_date'] <=> $b['start_date']);

            $run = null;

            foreach ($entries as $entry) {
                if ($run && Carbon::parse($run['end_date'])->addDay()->format('Y-m-d') === $entry['start_date']) {
                    $run['end_date'] = $entry['start_date'];

                    continue;
                }

                if ($run) {
                    $joined[] = $run;
                }

                $run = $entry;
            }

            if ($run) {
                $joined[] = $run;
            }
        }

        usort($joined, fn ($a, $b) => $a['start_date'] <=> $b['start_date']);

        return $joined;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<string, mixed>|string
     */
    private static function parseRow(array $cells, int $line): array|string
    {
        $name = (string) ($cells[0] ?? '');

        if ($name === '') {
            return "السطر {$line}: لا اسم للحدث.";
        }

        $from = self::date($cells[1] ?? null);

        if (! $from) {
            return "السطر {$line}: تاريخ البداية غير مفهوم — اكتبه هكذا 2026-09-06.";
        }

        // A single-day entry needs no end date, and the sheet usually omits it.
        $to = self::date($cells[2] ?? null) ?? $from;

        if ($to->lt($from)) {
            return "السطر {$line}: النهاية قبل البداية.";
        }

        $weekdays = self::weekdays((string) ($cells[4] ?? ''));

        if ($weekdays === false) {
            return "السطر {$line}: أيام الأسبوع غير مفهومة — اكتبها هكذا: الأحد، الاثنين.";
        }

        return [
            'line' => $line,
            'event_name' => $name,
            'start_date' => $from->format('Y-m-d'),
            'end_date' => $to->format('Y-m-d'),
            'is_attendance_period' => in_array(mb_strtolower((string) ($cells[3] ?? '')), self::PERIOD_WORDS, true),
            'weekdays' => $weekdays,
            'stage_names' => self::names((string) ($cells[5] ?? '')),
            'description' => ((string) ($cells[6] ?? '')) ?: null,
            'formative_note' => ((string) ($cells[7] ?? '')) ?: null,
        ];
    }

    /**
     * Write what was read into the calendar.
     *
     * Separate from reading so that what lands in the year can be tested
     * without a file: Livewire hands a component an empty temporary upload
     * however the file is faked, so a test driven through the screen can only
     * check that a sheet was accepted.
     *
     * An entry with the same name and start date is updated rather than
     * duplicated, so the same sheet may be uploaded twice — which is what
     * happens when a row is corrected and the file sent again.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{written: int, errors: array<int, string>}
     */
    public static function apply(array $rows, ?User $by = null): array
    {
        $written = 0;
        $errors = [];

        foreach ($rows as $row) {
            $stages = self::stagesFor($row['stage_names']);

            if ($stages['missing'] !== []) {
                $errors[] = 'السطر '.$row['line'].': لا برنامج بهذا الاسم — '.implode('، ', $stages['missing']);

                continue;
            }

            // Matched as a date and not as text: `start_date` is cast, so it
            // is stored as `Y-m-d H:i:s` and a plain comparison never finds
            // the row the last import wrote — every upload would add a second
            // copy of the same entry.
            $existing = AcademicCalendarEvent::where('event_name', $row['event_name'])
                ->whereDate('start_date', $row['start_date'])
                ->first();

            $values = [
                'end_date' => $row['end_date'],
                'is_attendance_period' => $row['is_attendance_period'],
                'weekdays' => $row['weekdays'] ?: null,
                'stage_ids' => $stages['ids'] ?: null,
                'description' => $row['description'],
                'formative_note' => $row['formative_note'],
                'is_visible' => true,
                'created_by_id' => $by?->id,
                'created_by_type' => $by ? $by::class : null,
            ];

            if ($existing) {
                $existing->update($values);
            } else {
                AcademicCalendarEvent::create($values + [
                    'event_name' => $row['event_name'],
                    'start_date' => $row['start_date'],
                ]);
            }

            $written++;
        }

        // The periods decide which days are working days, and they are read
        // once and remembered — so what was just written has to be let go of.
        AcademicCalendarEvent::forgetPeriodCache();

        return ['written' => $written, 'errors' => $errors];
    }

    /**
     * Resolve programme names to ids, reporting the ones nothing answers to.
     *
     * @param  array<int, string>  $names
     * @return array{ids: array<int, int>, missing: array<int, string>}
     */
    public static function stagesFor(array $names): array
    {
        if ($names === []) {
            return ['ids' => [], 'missing' => []];
        }

        $found = Stage::whereIn('name', $names)->pluck('id', 'name');

        return [
            'ids' => $found->values()->all(),
            'missing' => array_values(array_diff($names, $found->keys()->all())),
        ];
    }

    /**
     * A blank sheet with the columns named and two rows showing the shape.
     *
     * The BOM is what makes Excel open a UTF-8 CSV as Arabic rather than as
     * mojibake, which is the difference between a template somebody uses and
     * one he gives up on.
     */
    public static function template(): string
    {
        $rows = [
            ['الفصل الدراسي الأول', '2026-09-06', '2026-12-17', 'فترة دوام', 'الأحد، الاثنين، الثلاثاء، الأربعاء، الخميس', '', 'أيام الدوام', ''],
            ['إجازة اليوم الوطني', '2026-09-23', '2026-09-24', 'حدث', '', '', '', 'يومٌ نشكر فيه نعمة الأمن'],
        ];

        $out = "\u{FEFF}".implode(',', self::COLUMNS)."\n";

        foreach ($rows as $row) {
            $out .= implode(',', array_map(
                static fn (string $cell) => str_contains($cell, ',') ? '"'.$cell.'"' : $cell,
                $row,
            ))."\n";
        }

        return $out;
    }

    /**
     * A spreadsheet hands back a date cell as a DateTime and a typed one as
     * text, so both are accepted and anything else is refused rather than
     * guessed at.
     */
    private static function date(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        try {
            return Carbon::parse($text)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, int>|false false when a name was written that is not a day
     */
    private static function weekdays(string $value): array|false
    {
        $value = trim($value);

        // Empty means every day the period covers, which is how the calendar
        // reads an empty weekday list already.
        if ($value === '') {
            return [];
        }

        $days = [];

        foreach (preg_split('/[،,\/\-]+/u', $value) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (! isset(self::WEEKDAYS[$part])) {
                return false;
            }

            $days[] = self::WEEKDAYS[$part];
        }

        sort($days);

        return array_values(array_unique($days));
    }

    /** @return array<int, string> */
    private static function names(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[،,]+/u', $value))));
    }

    /** @param  array<int, mixed>  $cells */
    private static function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * A CSV read as a workbook throws, and an upload arrives under a temporary
     * name with no extension at all — so the format is named rather than
     * guessed from the path.
     */
    private static function readerFor(string $extension): ReaderInterface
    {
        return in_array(strtolower(ltrim($extension, '.')), ['csv', 'txt'], true)
            ? new CsvReader
            : new XlsxReader;
    }
}
