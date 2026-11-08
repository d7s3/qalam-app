<?php

use App\Models\Circle;
use App\Models\ReadingSession;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Student;
use App\Models\StudentBook;
use App\Models\StudentSelfProgramEntry;
use App\Services\ReadingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Asked for by Faisal, 8 Nov 2026, after the reading app نديم: shelves, a focus
 * timer, a streak and figures, quotes, and the cohort's week — with every page
 * read counted in المقروء too.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:00:00');

    $this->circle = Circle::factory()->create();
    $this->student = Student::factory()->create(['name' => 'عمر محمد', 'circle_id' => $this->circle->id]);
    $this->book = StudentBook::factory()->create(['student_id' => $this->student->id, 'current_page' => 20]);
    $this->reading = app(ReadingService::class);
});

afterEach(fn () => Carbon::setTestNow());

it('moves a book forward and finishes it on its last page', function () {
    $session = $this->reading->advance($this->book, 50);

    expect($session->pages)->toBe(30)
        ->and($this->book->fresh()->current_page)->toBe(50);

    $this->reading->advance($this->book->fresh(), 400);

    expect($this->book->fresh())
        ->current_page->toBe(300)
        ->shelf->toBe(StudentBook::READ)
        ->and($this->book->fresh()->finished_on->toDateString())->toBe('2026-11-10');
});

it('counts the pages in المقروء when the week asks for reading', function () {
    $week = SelfProgramWeek::create([
        'stage_id' => $this->circle->stage_id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-11-08',
        'ends_on' => '2026-11-14',
    ]);
    $maqrou = SelfProgramItem::create([
        'self_program_week_id' => $week->id,
        'track' => SelfProgramTrack::MAQROU,
        'description' => 'كتاب الأسبوع',
        'target_amount' => 100,
        'unit' => 'صفحة',
    ]);

    $this->reading->advance($this->book, 35);
    $this->reading->advance($this->book->fresh(), 45);

    expect((float) StudentSelfProgramEntry::where('self_program_item_id', $maqrou->id)->sum('amount_done'))->toEqual(25.0);
});

it('times a focus session and grows the plant', function () {
    $session = $this->reading->start($this->book);

    Carbon::setTestNow('2026-11-10 10:25:00');
    $done = $this->reading->finish($session, 40);

    expect($done->minutes)->toBe(25)
        ->and($done->pages)->toBe(20)
        ->and($this->reading->plant($this->student))->toBe(['stage' => 'بذرة', 'sessions' => 1, 'next' => 3]);
});

it('counts a timer left running as four hours at most', function () {
    $session = $this->reading->start($this->book);

    Carbon::setTestNow('2026-11-11 10:00:00');

    expect($this->reading->finish($session, 21)->minutes)->toBe(ReadingService::LONGEST_SESSION);
});

it('keeps a streak of days read, counting yesterday while today is not over', function () {
    foreach (['2026-11-07', '2026-11-08', '2026-11-09'] as $day) {
        ReadingSession::factory()->create(['student_id' => $this->student->id, 'ended_at' => "{$day} 20:00:00"]);
    }
    ReadingSession::factory()->create(['student_id' => $this->student->id, 'ended_at' => '2026-11-01 20:00:00']);

    expect($this->reading->streak($this->student))->toBe(['current' => 3, 'best' => 3]);
});

it('ranks the cohort by pages read this week', function () {
    $friend = Student::factory()->create(['name' => 'خالد', 'circle_id' => $this->circle->id]);
    ReadingSession::factory()->create(['student_id' => $friend->id, 'pages' => 40]);
    ReadingSession::factory()->create(['student_id' => $this->student->id, 'pages' => 15]);
    ReadingSession::factory()->create(['student_id' => $friend->id, 'pages' => 99, 'ended_at' => '2026-11-01 10:00:00']);

    expect($this->reading->cohortWeek($this->student)->map(fn ($row) => [$row['student']->name, $row['pages']])->all())
        ->toBe([['خالد', 40], ['عمر محمد', 15]]);
});

describe('the page', function () {
    it('adds a book, logs a page in Arabic digits and keeps a quote', function () {
        Livewire::actingAs($this->student, 'student')
            ->test('student.library')
            ->set('title', 'مدخل لفهم الأدب')
            ->set('totalPages', '١٥٠')
            ->call('addBook')
            ->assertSee('مدخل لفهم الأدب');

        $added = StudentBook::where('title', 'مدخل لفهم الأدب')->first();

        Livewire::actingAs($this->student, 'student')
            ->test('student.library')
            ->set("reached.{$added->id}", '٣٠')
            ->call('logPage', $added->id)
            ->set('quote', 'العلم صيد والكتابة قيده')
            ->set('quotePage', '12')
            ->call('addQuote', $added->id);

        expect($added->fresh()->current_page)->toBe(30)
            ->and($added->quotes()->value('body'))->toBe('العلم صيد والكتابة قيده');
    });

    it('runs a focus session from start to finish', function () {
        $page = Livewire::actingAs($this->student, 'student')
            ->test('student.library')
            ->call('startSession', $this->book->id)
            ->assertSee('جلسة قراءة');

        Carbon::setTestNow('2026-11-10 10:30:00');

        $page->set('sessionPage', '35')->call('finishSession')->assertSet('sessionId', null);

        expect(ReadingSession::first())->minutes->toBe(30)->pages->toBe(15);
    });

    it('will not touch another student\'s book', function () {
        $theirs = StudentBook::factory()->create();

        Livewire::actingAs($this->student, 'student')
            ->test('student.library')
            ->set("reached.{$theirs->id}", '50')
            ->call('logPage', $theirs->id)
            ->assertStatus(404);
    });

    it('opens from the student\'s menu', function () {
        $this->actingAs($this->student, 'student')
            ->get(route('student.library'))
            ->assertOk()
            ->assertSee('مكتبتي');
    });
});
