<?php

use App\Models\Circle;
use App\Models\SelfProgramDayOverride;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentSelfProgramEntry;
use App\Services\SelfProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The programme's promise is that the student is free with his week — he may
 * do all of it on one day. Two fields are exceptions, and both for a reason.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-08 09:00:00');

    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->student = Student::factory()->create([
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
    ]);

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $this->prep = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::TAHDHEER,
        'target_amount' => 4,
        'unit' => 'درس',
    ]);

    $this->memorised = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'target_amount' => 10,
        'unit' => 'بيت',
    ]);

    $this->read = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::MAQROU,
        'target_amount' => 20,
        'unit' => 'صفحة',
    ]);

    $this->service = app(SelfProgramService::class);
});

afterEach(fn () => Carbon::setTestNow());

it('lets the free fields be done on any day, or all on one', function () {
    $this->service->record($this->student, $this->read, 20, Carbon::parse('2026-09-08'));

    expect((float) StudentSelfProgramEntry::where('self_program_item_id', $this->read->id)->sum('amount_done'))
        ->toBe(20.00);
});

it('binds التحضير to the day it was set for', function () {
    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->prep->id,
        'day_date' => '2026-09-08',
        'content' => 'شرح الدرس الثالث',
    ]);

    expect($this->service->dayIsOpenFor($this->prep, $this->student, Carbon::parse('2026-09-08')))->toBeTrue();

    // Preparing on Thursday for Sunday's lesson is not preparation.
    expect($this->service->dayIsOpenFor($this->prep, $this->student, Carbon::parse('2026-09-10')))->toBeFalse();

    expect(fn () => $this->service->record($this->student, $this->prep, 1, Carbon::parse('2026-09-10')))
        ->toThrow(InvalidArgumentException::class);
});

it('leaves التحضير free when no day was set for it', function () {
    // Nothing binds it, so it behaves like the rest rather than refusing every
    // day of the week.
    expect($this->service->dayIsOpenFor($this->prep, $this->student, Carbon::parse('2026-09-10')))->toBeTrue();

    $this->service->record($this->student, $this->prep, 1, Carbon::parse('2026-09-10'));

    expect(StudentSelfProgramEntry::where('self_program_item_id', $this->prep->id)->count())->toBe(1);
});

it('refuses المحفوظ until the student says he recited it', function () {
    expect(fn () => $this->service->record($this->student, $this->memorised, 5))
        ->toThrow(InvalidArgumentException::class);

    expect(StudentSelfProgramEntry::where('self_program_item_id', $this->memorised->id)->count())->toBe(0);
});

it('counts المحفوظ once he says he has', function () {
    $this->service->record($this->student, $this->memorised, 5, null, StudentSelfProgramEntry::SOURCE_STUDENT, true);

    expect((float) StudentSelfProgramEntry::where('self_program_item_id', $this->memorised->id)->sum('amount_done'))
        ->toBe(5.00);
});

it('lets a cleared entry through without a hearing', function () {
    $this->service->record($this->student, $this->memorised, 5, null, StudentSelfProgramEntry::SOURCE_STUDENT, true);

    // Erasing what he wrote is not a claim to have recited anything.
    $this->service->record($this->student, $this->memorised, 0);

    expect(StudentSelfProgramEntry::where('self_program_item_id', $this->memorised->id)->count())->toBe(0);
});

it('asks the student before it counts, and writes nothing if he says not yet', function () {
    $component = Livewire::actingAs($this->student, 'student')
        ->test('student.self-program')
        ->set("amounts.{$this->memorised->id}", 5)
        ->call('save', $this->memorised->id);

    // Nothing is written while the question stands.
    expect(StudentSelfProgramEntry::count())->toBe(0);
    $component->assertSet('askingRecitationFor', $this->memorised->id);

    $component->call('denyRecitation');

    // Not a zero either: a zero says he did none of it, and he is saying he
    // did it and has not recited it yet.
    expect(StudentSelfProgramEntry::count())->toBe(0);
    $component->assertSet('askingRecitationFor', null);
});

it('writes it when he says yes', function () {
    Livewire::actingAs($this->student, 'student')
        ->test('student.self-program')
        ->set("amounts.{$this->memorised->id}", 5)
        ->call('save', $this->memorised->id)
        ->call('confirmRecitation')
        ->assertSet('askingRecitationFor', null);

    expect((float) StudentSelfProgramEntry::where('self_program_item_id', $this->memorised->id)->sum('amount_done'))
        ->toBe(5.00);
});

it('does not ask a recitation the teacher already heard', function () {
    // The bridge writes what a teacher graded, and that hearing already
    // happened — asking the student to confirm it would be asking twice.
    $this->service->record(
        $this->student,
        $this->memorised,
        3,
        null,
        StudentSelfProgramEntry::SOURCE_TASMEEH,
    );

    expect((float) StudentSelfProgramEntry::where('self_program_item_id', $this->memorised->id)->sum('amount_done'))
        ->toBe(3.00);
});
