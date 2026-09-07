<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Manager;
use App\Models\Stage;
use App\Support\AcademicCalendarSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A calendar is drawn in a spreadsheet long before anybody types it into an
 * application. Retyping thirty rows into a form is how a calendar comes to be
 * half-entered and then trusted.
 *
 * Reading and writing are tested apart because Livewire hands a component an
 * empty temporary upload however the file is faked — so a test driven through
 * the screen can only check that a sheet was accepted, and what the sheet said
 * has to be checked against the reader itself.
 */
beforeEach(function () {
    $this->manager = Manager::factory()->create();
    $this->programme = Stage::factory()->create(['name' => 'برنامج الحفظ']);
});

/** Write a sheet the way the academy writes one, and read it back. */
function readSheet(string $body): array
{
    $path = tempnam(sys_get_temp_dir(), 'cal').'.csv';

    file_put_contents($path, "\u{FEFF}".implode(',', AcademicCalendarSheet::COLUMNS)."\n".$body);

    try {
        return AcademicCalendarSheet::read($path, 'csv');
    } finally {
        @unlink($path);
    }
}

it('reads a term and an event off one sheet', function () {
    $read = readSheet(
        "الفصل الأول,2026-09-06,2026-12-17,فترة دوام,\"الأحد، الاثنين\",,أيام الدوام,\n".
        "إجازة اليوم الوطني,2026-09-23,2026-09-24,حدث,,,,يومٌ نشكر فيه نعمة الأمن\n"
    );

    expect($read['errors'])->toBe([]);

    AcademicCalendarSheet::apply($read['rows'], $this->manager);

    $term = AcademicCalendarEvent::where('event_name', 'الفصل الأول')->firstOrFail();

    expect($term->is_attendance_period)->toBeTrue();
    expect($term->weekdays)->toBe([1, 2]);
    expect($term->end_date->format('Y-m-d'))->toBe('2026-12-17');

    $holiday = AcademicCalendarEvent::where('event_name', 'إجازة اليوم الوطني')->firstOrFail();

    expect($holiday->is_attendance_period)->toBeFalse();
    // The formative note is the column the student reads, not the description.
    expect($holiday->formative_note)->toBe('يومٌ نشكر فيه نعمة الأمن');
});

it('gives a single-day entry the end date it did not write', function () {
    $read = readSheet("يوم مفتوح,2026-10-01,,حدث,,,,\n");

    expect($read['rows'][0]['end_date'])->toBe('2026-10-01');
});

it('binds an entry to the programmes it names', function () {
    $read = readSheet("لقاء الحفظ,2026-10-05,2026-10-05,حدث,,برنامج الحفظ,,\n");

    AcademicCalendarSheet::apply($read['rows'], $this->manager);

    expect(AcademicCalendarEvent::where('event_name', 'لقاء الحفظ')->firstOrFail()->stage_ids)
        ->toBe([$this->programme->id]);
});

it('keeps the good rows when one is bad, and says which', function () {
    $read = readSheet(
        "سليم,2026-10-05,2026-10-06,حدث,,,,\n".
        "تاريخ مكسور,ليس تاريخاً,,حدث,,,,\n".
        "برنامج مجهول,2026-10-07,,حدث,,لا وجود له,,\n".
        "سليم آخر,2026-10-08,,حدث,,,,\n"
    );

    $applied = AcademicCalendarSheet::apply($read['rows'], $this->manager);

    // One bad date should not cost the other twenty-nine.
    expect(AcademicCalendarEvent::count())->toBe(2);
    expect($applied['written'])->toBe(2);

    // The broken date is caught reading, the unknown programme writing, and
    // both name their line so the sheet can be corrected rather than guessed at.
    expect($read['errors'][0])->toContain('السطر 3');
    expect($applied['errors'][0])->toContain('السطر 4');
});

it('refuses a weekday nobody could read', function () {
    $read = readSheet("فترة,2026-10-05,2026-10-30,فترة دوام,\"الأحد، يوم غريب\",,,\n");

    expect($read['rows'])->toBe([]);
    expect($read['errors'][0])->toContain('أيام الأسبوع');
});

it('refuses an end before a beginning', function () {
    $read = readSheet("مقلوب,2026-10-30,2026-10-05,حدث,,,,\n");

    expect($read['rows'])->toBe([]);
    expect($read['errors'][0])->toContain('النهاية قبل البداية');
});

it('updates rather than duplicates when the same sheet comes twice', function () {
    $body = "الفصل الأول,2026-09-06,2026-12-17,فترة دوام,,,وصف أول,\n";

    AcademicCalendarSheet::apply(readSheet($body)['rows'], $this->manager);
    AcademicCalendarSheet::apply(readSheet(str_replace('وصف أول', 'وصف مصحَّح', $body))['rows'], $this->manager);

    // A row corrected and the file sent again is the ordinary case.
    expect(AcademicCalendarEvent::count())->toBe(1);
    expect(AcademicCalendarEvent::first()->description)->toBe('وصف مصحَّح');
});

it('lets a period it wrote decide the working days at once', function () {
    // The periods are read once and remembered, so an import that did not let
    // go of them would write a year the application went on ignoring.
    // A Wednesday: a working day by default, since the calendar keeps Sunday
    // to Thursday when no period says otherwise.
    expect(AcademicCalendarEvent::isWorkingDay('2026-10-07'))->toBeTrue();

    AcademicCalendarSheet::apply(
        readSheet("الفصل الأول,2026-09-06,2026-12-17,فترة دوام,\"الأحد، الاثنين\",,,\n")['rows'],
        $this->manager,
    );

    // The term keeps Sundays and Mondays, so the Wednesday is no longer one.
    expect(AcademicCalendarEvent::isWorkingDay('2026-10-07'))->toBeFalse();
});

it('accepts a sheet from the screen and puts it down afterwards', function () {
    $component = Livewire::actingAs($this->manager, 'manager')
        ->test('manager.academic-calendar')
        ->set('calendarSheet', UploadedFile::fake()->create('calendar.csv', 2))
        ->call('importSheet')
        ->assertHasNoErrors();

    expect($component->get('calendarSheet'))->toBeNull();
});

it('refuses a file that is not a sheet', function () {
    Livewire::actingAs($this->manager, 'manager')
        ->test('manager.academic-calendar')
        ->set('calendarSheet', UploadedFile::fake()->create('notes.pdf', 10))
        ->call('importSheet')
        ->assertHasErrors('calendarSheet');
});

it('offers a template with the columns named', function () {
    $csv = AcademicCalendarSheet::template();

    foreach (AcademicCalendarSheet::COLUMNS as $column) {
        expect($csv)->toContain($column);
    }

    // The BOM is what makes Excel open a UTF-8 CSV as Arabic rather than as
    // mojibake — the difference between a template used and one given up on.
    expect(str_starts_with($csv, "\u{FEFF}"))->toBeTrue();
});

/**
 * The other shape: the academy's own calendar, drawn as a grid of weeks with
 * Hijri day numbers in the cells. It is the sheet people actually have, and it
 * has to be read as it stands rather than retyped into a column layout.
 */
function readGridSheet(string $body): array
{
    $path = tempnam(sys_get_temp_dir(), 'grid').'.csv';

    file_put_contents($path, "\u{FEFF}".$body);

    try {
        return AcademicCalendarSheet::read($path, 'csv');
    } finally {
        @unlink($path);
    }
}

it('reads a week grid written in Hijri, right to left', function () {
    $read = readGridSheet(<<<'CSV'
    تقويم الفصل الدراسي الأول ١٤٤٨ هـ,,,,,,,,
    السبت,الجمعة,الخميس,الأربعاء,الثلاثاء,الاثنين,الأحد,الأسبوع,الشهر
    "٢٣
    المجلس الأسبوعي",٢٢,٢١,٢٠,١٩,١٨,"١٧
    تذكرة السامع",١,ربيع الأول
    CSV);

    expect($read['errors'])->toBe([]);

    $names = collect($read['rows'])->pluck('event_name', 'start_date');

    // ١٧ ربيع الأول ١٤٤٨ is a Sunday, and ٢٣ the Saturday that closes its week.
    expect($names['2026-08-30'])->toBe('تذكرة السامع')
        ->and($names['2026-09-05'])->toBe('المجلس الأسبوعي');
});

it('turns the month inside a week where the days fall back', function () {
    // ربيع الأول ١٤٤٨ closes at ٢٩, so the week that holds it opens ربيع الآخر
    // on its last day — and the sheet says so only by starting the count again.
    $read = readGridSheet(<<<'CSV'
    تقويم ١٤٤٨ هـ,,,,,,,,
    السبت,الجمعة,الخميس,الأربعاء,الثلاثاء,الاثنين,الأحد,الأسبوع,الشهر
    "١
    رأس الشهر","٢٩
    آخر الشهر",٢٨,٢٧,٢٦,٢٥,٢٤,٢,ربيع الأول
    CSV);

    expect($read['errors'])->toBe([]);

    $names = collect($read['rows'])->pluck('event_name', 'start_date');

    expect($names['2026-09-11'])->toBe('آخر الشهر')
        ->and($names['2026-09-12'])->toBe('رأس الشهر');
});

it('joins a name repeated on touching days into one span', function () {
    $read = readGridSheet(<<<'CSV'
    تقويم ١٤٤٨ هـ,,,,,,,,
    السبت,الجمعة,الخميس,الأربعاء,الثلاثاء,الاثنين,الأحد,الأسبوع,الشهر
    ٢٣,٢٢,٢١,"٢٠
    اليوم الوطني","١٩
    اليوم الوطني",١٨,١٧,١,ربيع الأول
    CSV);

    expect($read['rows'])->toHaveCount(1)
        ->and($read['rows'][0]['start_date'])->toBe('2026-09-01')
        ->and($read['rows'][0]['end_date'])->toBe('2026-09-02');
});

it('still reads the column template it offers', function () {
    $read = readSheet("مجلس,2026-09-05,2026-09-05,حدث,,,,\n");

    expect($read['errors'])->toBe([])
        ->and($read['rows'][0]['event_name'])->toBe('مجلس');
});
