<?php

use App\Models\AcademicCalendarEvent;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Services\SelfProgramYearBuilder;
use App\Support\SelfProgramSheet;
use App\Support\SelfProgramTrack;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Nobody fills two hundred and sixty entries by hand, so the year is laid out at
 * once and filled by whichever route suits the supervisor.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-06 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->builder = app(SelfProgramYearBuilder::class);
});

it('lays out consecutive weeks from a date', function () {
    $result = $this->builder->generate(Carbon::parse('2026-09-06'), 4, $this->stage->id);

    expect($result['created'])->toBe(4);

    $weeks = SelfProgramWeek::orderBy('week_number')->get();

    expect($weeks->pluck('week_number')->all())->toBe([1, 2, 3, 4])
        ->and($weeks->first()->starts_on->toDateString())->toBe('2026-09-06')
        ->and($weeks->first()->ends_on->toDateString())->toBe('2026-09-12')
        ->and($weeks->last()->starts_on->toDateString())->toBe('2026-09-27')
        // Every generated week arrives holding all five fields.
        ->and($weeks->first()->items()->count())->toBe(5);
});

it('skips a week the academy never meets in', function () {
    // A closure covering the second week entirely.
    AcademicCalendarEvent::create([
        'event_name' => 'إجازة',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-01',
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5],
        'stage_ids' => [$this->stage->id],
        'excluded_dates' => ['2026-09-13', '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19'],
    ]);
    AcademicCalendarEvent::forgetPeriodCache();

    $result = $this->builder->generate(Carbon::parse('2026-09-06'), 3, $this->stage->id);

    expect($result)->toBe(['created' => 2, 'skipped' => 1]);

    // Numbering follows the weeks actually taught, with no gap in the sequence.
    expect(SelfProgramWeek::orderBy('week_number')->pluck('starts_on')
        ->map(fn ($d) => Carbon::parse($d)->toDateString())->all())
        ->toBe(['2026-09-06', '2026-09-20']);
});

it('does not double the year when run twice', function () {
    $this->builder->generate(Carbon::parse('2026-09-06'), 3, $this->stage->id);
    $second = $this->builder->generate(Carbon::parse('2026-09-06'), 3, $this->stage->id);

    expect($second['created'])->toBe(0)
        ->and(SelfProgramWeek::count())->toBe(3);
});

it('copies one week across the rest, leaving the source alone', function () {
    $this->builder->generate(Carbon::parse('2026-09-06'), 3, $this->stage->id);

    $weeks = SelfProgramWeek::with('items')->orderBy('week_number')->get();
    $source = $weeks->first();
    $source->items()->where('track', SelfProgramTrack::QuranWird->value)
        ->update(['description' => 'سورة الملك', 'target_amount' => 20]);

    $written = $this->builder->copyAcross($source->fresh('items'), $weeks);

    expect($written)->toBe(2);

    foreach ($weeks as $week) {
        $wird = $week->fresh('items')->items->firstWhere('track', SelfProgramTrack::QuranWird);
        expect((float) $wird->target_amount)->toBe(20.0)
            ->and($wird->description)->toBe('سورة الملك');
    }
});

it('divides a yearly total across the weeks without losing the remainder', function () {
    $this->builder->generate(Carbon::parse('2026-09-06'), 3, $this->stage->id);
    $weeks = SelfProgramWeek::orderBy('week_number')->get();

    // 100 over three weeks: 33.33, 33.33, then the rest.
    $this->builder->distribute([SelfProgramTrack::QuranWird->value => 100], $weeks);

    $amounts = SelfProgramWeek::with('items')->orderBy('week_number')->get()
        ->map(fn ($w) => (float) $w->items->firstWhere('track', SelfProgramTrack::QuranWird)->target_amount);

    expect($amounts->all())->toBe([33.33, 33.33, 33.34])
        ->and(round($amounts->sum(), 2))->toBe(100.0);
});

describe('importing a sheet', function () {
    beforeEach(function () {
        $this->path = sys_get_temp_dir().'/self-program-'.uniqid().'.csv';
        $this->builder->generate(Carbon::parse('2026-09-06'), 2, $this->stage->id);
    });

    afterEach(function () {
        @unlink($this->path);
    });

    it('reads Arabic field names off a handed-over sheet', function () {
        file_put_contents($this->path, "\u{FEFF}الأسبوع,المجال,المحتوى,المقدار,الوحدة\n"
            ."1,الورد القرآني,سورة الملك,20,صفحة\n"
            ."1,المسموع,درس التجويد,3,درس\n"
            ."2,الورد القرآني,سورة القلم,15,صفحة\n");

        $result = $this->builder->import($this->path, $this->stage->id);

        expect($result['written'])->toBe(3)
            ->and($result['errors'])->toBe([]);

        $first = SelfProgramWeek::with('items')->where('week_number', 1)->first();

        expect((float) $first->items->firstWhere('track', SelfProgramTrack::QuranWird)->target_amount)->toBe(20.0)
            ->and($first->items->firstWhere('track', SelfProgramTrack::Masmou)->description)->toBe('درس التجويد');
    });

    it('names the row when a field is not recognised', function () {
        file_put_contents($this->path, "الأسبوع,المجال,المحتوى,المقدار,الوحدة\n"
            ."1,الرياضيات,شيء,5,درس\n");

        $result = $this->builder->import($this->path, $this->stage->id);

        expect($result['written'])->toBe(0)
            ->and($result['errors'][0])->toContain('السطر 2')
            ->and($result['errors'][0])->toContain('الرياضيات');
    });

    it('names the row when the amount is not a number', function () {
        file_put_contents($this->path, "الأسبوع,المجال,المحتوى,المقدار,الوحدة\n"
            ."1,الورد القرآني,سورة الملك,كثير,صفحة\n");

        expect($this->builder->import($this->path, $this->stage->id)['errors'][0])
            ->toContain('المقدار يجب أن يكون رقماً');
    });

    it('refuses to invent a week the calendar does not hold', function () {
        file_put_contents($this->path, "الأسبوع,المجال,المحتوى,المقدار,الوحدة\n"
            ."9,الورد القرآني,سورة الملك,20,صفحة\n");

        $result = $this->builder->import($this->path, $this->stage->id);

        expect($result['written'])->toBe(0)
            ->and($result['errors'][0])->toContain('لا يوجد أسبوع رقم 9')
            ->and(SelfProgramWeek::count())->toBe(2);
    });

    it('keeps the wird in pages whatever unit the sheet names', function () {
        file_put_contents($this->path, "الأسبوع,المجال,المحتوى,المقدار,الوحدة\n"
            ."1,quran_wird,سورة الملك,20,حديث\n");

        $this->builder->import($this->path, $this->stage->id);

        expect(SelfProgramWeek::with('items')->where('week_number', 1)->first()
            ->items->firstWhere('track', SelfProgramTrack::QuranWird)->unit)
            ->toBe('صفحة');
    });

    it('offers a template shaped the way it reads', function () {
        $template = SelfProgramSheet::template(2);

        expect($template)->toStartWith("\u{FEFF}الأسبوع,المجال,المحتوى,المقدار,الوحدة")
            ->and(substr_count($template, "\n"))->toBe(11);
    });
});
