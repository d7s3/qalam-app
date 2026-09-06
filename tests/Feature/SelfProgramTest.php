<?php

use App\Models\Circle;
use App\Models\SelfProgramDayOverride;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentSelfProgramEntry;
use App\Models\Supervisor;
use App\Services\SelfProgramService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The self programme is authored a week at a time and done a day at a time, and
 * the two are joined by arithmetic rather than by stored rows: what a day asks
 * of a student follows from what the week asks and how much of it is left.
 */
beforeEach(function () {
    // A Sunday, so the week below runs Sunday to Thursday.
    Carbon::setTestNow('2026-09-06 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->stage->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $this->service = app(SelfProgramService::class);
});

/** The fields are keys now rather than enum cases, so the helper takes one. */
function track(SelfProgramWeek $week, string $key, float $target): SelfProgramItem
{
    $track = SelfProgramTrack::findByKey($key);

    return SelfProgramItem::create([
        'self_program_week_id' => $week->id,
        'track' => $key,
        'description' => 'محتوى الأسبوع',
        'target_amount' => $target,
        'unit' => $track?->defaultUnit() ?? 'وحدة',
    ]);
}

it('spreads a week across its working days only', function () {
    $item = track($this->week, SelfProgramTrack::QURAN_WIRD, 20);

    $plan = $this->service->dailyPlan($item, $this->student);

    // Sunday to Thursday: the calendar's ordinary week, Friday and Saturday out.
    expect(array_keys($plan))->toBe([
        '2026-09-06', '2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10',
    ]);
    expect($plan['2026-09-06'])->toBe(4.0);
});

it('raises the remaining days when a student falls behind', function () {
    $item = track($this->week, SelfProgramTrack::QURAN_WIRD, 20);

    // Sunday passes with nothing done.
    $plan = $this->service->dailyPlan($item, $this->student);
    expect($plan['2026-09-07'])->toBe(5.0);

    // Monday's five are done, so Tuesday still asks five of the fifteen left.
    $this->service->record($this->student, $item, 5, Carbon::parse('2026-09-07'));
    $plan = $this->service->dailyPlan($item, $this->student);
    expect($plan['2026-09-08'])->toBe(5.0);

    // A strong Tuesday eases the rest of the week.
    $this->service->record($this->student, $item, 7, Carbon::parse('2026-09-08'));
    $plan = $this->service->dailyPlan($item, $this->student);
    expect($plan['2026-09-09'])->toBe(4.0);
});

it('never asks for more than the week holds', function () {
    $item = track($this->week, SelfProgramTrack::QURAN_WIRD, 20);

    $this->service->record($this->student, $item, 20, Carbon::parse('2026-09-06'));

    $plan = $this->service->dailyPlan($item, $this->student);

    expect(array_sum(array_slice($plan, 1)))->toBe(0.0);
});

it('lets a teacher overrule the split for one student', function () {
    $item = track($this->week, SelfProgramTrack::QURAN_WIRD, 20);

    SelfProgramDayOverride::create([
        'self_program_item_id' => $item->id,
        'circle_id' => $this->circle->id,
        'day_date' => '2026-09-06',
        'amount' => 2,
    ]);
    SelfProgramDayOverride::create([
        'self_program_item_id' => $item->id,
        'student_id' => $this->student->id,
        'day_date' => '2026-09-06',
        'amount' => 1,
    ]);

    $plan = $this->service->dailyPlan($item, $this->student);

    // The student's own override beats the one written for his circle.
    expect($plan['2026-09-06'])->toBe(1.0);
});

it('reads a week as the mean of its tracks, not the sum of their amounts', function () {
    // Pages, lessons and hadiths do not add up; their percentages do.
    $wird = track($this->week, SelfProgramTrack::QURAN_WIRD, 20);
    $maqrou = track($this->week, SelfProgramTrack::MAQROU, 30);
    $masmou = track($this->week, SelfProgramTrack::MASMOU, 4);
    track($this->week, SelfProgramTrack::TAHDHEER, 3);
    track($this->week, SelfProgramTrack::MAHFOUDH, 5);

    $this->service->record($this->student, $wird, 18);
    $this->service->record($this->student, $maqrou, 30);
    $this->service->record($this->student, $masmou, 2);

    $progress = $this->service->weekProgress($this->student, $this->week->fresh('items'));

    // (90 + 100 + 50 + 0 + 0) / 5
    expect($progress['overall'])->toBe(48.0);
});

it('caps a track at full, so one cannot cover for another', function () {
    $wird = track($this->week, SelfProgramTrack::QURAN_WIRD, 10);
    track($this->week, SelfProgramTrack::MAQROU, 10);

    // Triple the wird; it still counts once.
    $this->service->record($this->student, $wird, 30);

    $progress = $this->service->weekProgress($this->student, $this->week->fresh('items'));

    expect($progress['overall'])->toBe(50.0);
});

it('leaves a track the supervisor did not set out of the reckoning', function () {
    $wird = track($this->week, SelfProgramTrack::QURAN_WIRD, 10);
    track($this->week, SelfProgramTrack::MAQROU, 0);

    $this->service->record($this->student, $wird, 10);

    // Only one track was asked for this week, and it is done.
    expect($this->service->weekProgress($this->student, $this->week->fresh('items'))['overall'])->toBe(100.0);
});

it('opens the enrichment programme at half the week', function () {
    $wird = track($this->week, SelfProgramTrack::QURAN_WIRD, 10);
    $maqrou = track($this->week, SelfProgramTrack::MAQROU, 10);

    $this->service->record($this->student, $wird, 4);
    expect($this->service->enrichmentUnlocked($this->student, $this->week->fresh('items')))->toBeFalse();

    $this->service->record($this->student, $maqrou, 6);
    expect($this->service->enrichmentUnlocked($this->student, $this->week->fresh('items')))->toBeTrue();
});

it('clears the day when a student unticks it rather than storing a nought', function () {
    $item = track($this->week, SelfProgramTrack::QURAN_WIRD, 20);

    $this->service->record($this->student, $item, 4);
    expect(StudentSelfProgramEntry::count())->toBe(1);

    $this->service->record($this->student, $item, 0);
    expect(StudentSelfProgramEntry::count())->toBe(0);
});

describe('opening a week', function () {
    beforeEach(function () {
        $this->nextWeek = SelfProgramWeek::create([
            'stage_id' => $this->stage->id,
            'program_type' => SelfProgramWeek::TYPE_SELF,
            'week_number' => 2,
            'starts_on' => '2026-09-13',
            'ends_on' => '2026-09-19',
        ]);
        track($this->week, SelfProgramTrack::QURAN_WIRD, 10);
    });

    it('holds the next week shut until its date', function () {
        expect($this->service->canOpen($this->student, $this->nextWeek))->toBeFalse();
    });

    it('keeps a finished week from opening the next when the circle has not asked for it', function () {
        $this->service->record($this->student, $this->week->items->first(), 10);

        expect($this->service->canOpen($this->student, $this->nextWeek))->toBeFalse();
    });

    it('opens the next week early once the circle allows it and the week is done', function () {
        $this->circle->update(['self_program_unlock_on_completion' => true]);
        $this->student->refresh();

        expect($this->service->canOpen($this->student, $this->nextWeek))->toBeFalse();

        $this->service->record($this->student, $this->week->items->first(), 10);

        expect($this->service->canOpen($this->student, $this->nextWeek->fresh()))->toBeTrue();
    });

    it('leaves a past week open so a student can still finish it', function () {
        Carbon::setTestNow('2026-09-15 08:00:00');

        expect($this->service->canOpen($this->student, $this->week))->toBeTrue();
    });
});

describe('the student page', function () {
    beforeEach(function () {
        $this->item = track($this->week, SelfProgramTrack::QURAN_WIRD, 20);
        // A field the student records for himself, whatever kind of circle he
        // is in — the wird of a memorising circle is his teacher's to write.
        $this->manual = track($this->week, SelfProgramTrack::MASMOU, 8);
    });

    it('shows the week and its suggested share for today', function () {
        $this->actingAs($this->student, 'student')
            ->get(route('student.self-program'))
            ->assertOk()
            ->assertSee('البرنامج الذاتي')
            ->assertSee('الورد القرآني');
    });

    it('records what the student confirms', function () {
        Livewire::actingAs($this->student, 'student')
            ->test('student.self-program')
            ->set("amounts.{$this->manual->id}", 6)
            ->call('save', $this->manual->id)
            ->assertHasNoErrors();

        expect(StudentSelfProgramEntry::where('student_id', $this->student->id)->sum('amount_done'))
            ->toEqual(6.0);
    });

    it('refuses an amount that is not a number', function () {
        Livewire::actingAs($this->student, 'student')
            ->test('student.self-program')
            ->set("amounts.{$this->manual->id}", 'كثير')
            ->call('save', $this->manual->id)
            ->assertHasErrors("amounts.{$this->manual->id}");
    });

    it('will not let a memorising circle\'s student write his own wird', function () {
        // It is recorded from his recitation; letting him confirm it again
        // stored the same reading twice.
        Livewire::actingAs($this->student, 'student')
            ->test('student.self-program')
            ->set("amounts.{$this->item->id}", 6)
            ->call('save', $this->item->id)
            ->assertStatus(403);

        expect(StudentSelfProgramEntry::count())->toBe(0);
    });

    it('says the wird comes from his teacher rather than offering a box', function () {
        $this->actingAs($this->student, 'student')
            ->get(route('student.self-program'))
            ->assertOk()
            ->assertSee('يُسجَّل تلقائياً من تسميعك عند معلمك');
    });

    it('lets him write his own wird when his circle does not memorise', function () {
        $this->circle->update(['is_quranic' => false]);

        Livewire::actingAs($this->student->fresh(), 'student')
            ->test('student.self-program')
            ->set("amounts.{$this->item->id}", 6)
            ->call('save', $this->item->id)
            ->assertHasNoErrors();

        expect(StudentSelfProgramEntry::where('student_id', $this->student->id)->sum('amount_done'))
            ->toEqual(6.0);
    });

    it('will not record against another stage\'s week', function () {
        $otherWeek = SelfProgramWeek::create([
            'stage_id' => Stage::factory()->create()->id,
            'program_type' => SelfProgramWeek::TYPE_SELF,
            'week_number' => 1,
            'starts_on' => '2026-09-06',
            'ends_on' => '2026-09-12',
        ]);
        $foreign = track($otherWeek, SelfProgramTrack::MASMOU, 20);

        Livewire::actingAs($this->student, 'student')
            ->test('student.self-program')
            ->set("amounts.{$foreign->id}", 5)
            ->call('save', $foreign->id)
            ->assertStatus(404);
    });
});

describe('the year tools', function () {
    beforeEach(function () {
        $this->supervisor = Supervisor::factory()->create();
        $this->supervisor->stages()->attach($this->stage->id);
    });

    it('lays out a year of weeks', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('yearStartsOn', '2026-09-13')
            ->set('yearWeeks', 5)
            ->call('generateYear')
            ->assertHasNoErrors();

        // Five new ones beside the week the outer setup already made.
        expect(SelfProgramWeek::where('stage_id', $this->stage->id)->count())->toBe(6);
    });

    it('refuses a year longer than the tool allows', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('yearWeeks', 200)
            ->call('generateYear')
            ->assertHasErrors('yearWeeks');
    });

    it('copies the open week across the rest', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('yearStartsOn', '2026-09-13')
            ->set('yearWeeks', 2)
            ->call('generateYear')
            ->call('openWeek', $this->week->id)
            ->set('rows.quran_wird.description', 'سورة الملك')
            ->set('rows.quran_wird.target_amount', 20)
            ->call('save')
            ->call('copyAcrossYear');

        $written = SelfProgramWeek::with('items')->where('stage_id', $this->stage->id)->get()
            ->filter(fn ($w) => (float) $w->items->firstWhere('track.key', SelfProgramTrack::QURAN_WIRD)?->target_amount === 20.0);

        expect($written)->toHaveCount(3);
    });

    it('divides a yearly total across the weeks', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('yearStartsOn', '2026-09-13')
            ->set('yearWeeks', 1)
            ->call('generateYear')
            ->set('annual.quran_wird', 60)
            ->call('distributeYear')
            ->assertHasNoErrors();

        $amounts = SelfProgramWeek::with('items')->where('stage_id', $this->stage->id)
            ->orderBy('week_number')->get()
            ->map(fn ($w) => (float) $w->items->firstWhere('track.key', SelfProgramTrack::QURAN_WIRD)->target_amount);

        expect($amounts->sum())->toBe(60.0);
    });

    /*
     * Reading a sheet is proved against real files in SelfProgramYearBuilderTest,
     * where every malformed row is exercised. Livewire's test harness hands the
     * component an empty temporary upload however the file is faked, so what is
     * checked here is the part that belongs to the component: that it accepts a
     * sheet, runs, and puts the file down afterwards.
     */
    it('accepts a sheet and clears it once read', function () {
        $component = Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->call('openWeek', $this->week->id)
            ->set('sheet', UploadedFile::fake()->create('year.csv', 2))
            ->call('importSheet')
            ->assertHasNoErrors();

        expect($component->get('sheet'))->toBeNull()
            ->and($component->get('importErrors'))->toBe([]);
    });

    it('refuses a file that is not a sheet', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('sheet', UploadedFile::fake()->create('notes.pdf', 10))
            ->call('importSheet')
            ->assertHasErrors('sheet');
    });

    it('refuses year tools for a stage the supervisor does not hold', function () {
        $stranger = Supervisor::factory()->create();
        $stranger->stages()->attach(Stage::factory()->create()->id);

        Livewire::actingAs($stranger, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('stageId', $this->stage->id)
            ->call('generateYear')
            ->assertStatus(403);
    });

    it('hands back a template shaped the way it reads', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->call('downloadTemplate')
            ->assertFileDownloaded('self-program-template.csv');
    });
});

describe('arrears on the student page', function () {
    beforeEach(function () {
        $this->item = track($this->week, SelfProgramTrack::QURAN_WIRD, 20);
        $this->service->record($this->student, $this->item, 5, Carbon::parse('2026-09-08'));

        // A fortnight on, so the week above is behind him.
        Carbon::setTestNow('2026-09-20 08:00:00');
    });

    it('shows what an ended week still owes', function () {
        $this->actingAs($this->student, 'student')
            ->get(route('student.self-program'))
            ->assertOk()
            ->assertSee('المتأخرات');
    });

    it('settles an arrear against the week it belongs to', function () {
        Livewire::actingAs($this->student, 'student')
            ->test('student.self-program')
            ->set("settleAmounts.{$this->item->id}", 15)
            ->call('settle', $this->item->id)
            ->assertHasNoErrors();

        expect($this->service->arrears($this->student))->toBe([])
            ->and($this->service->weekProgress($this->student, $this->week->fresh('items'))['overall'])
            ->toBe(100.0);
    });

    it('refuses to settle a track that owes nothing', function () {
        $this->service->record($this->student, $this->item, 20, Carbon::parse('2026-09-20'));

        Livewire::actingAs($this->student, 'student')
            ->test('student.self-program')
            ->set("settleAmounts.{$this->item->id}", 5)
            ->call('settle', $this->item->id)
            ->assertStatus(404);
    });

    it('refuses an empty amount', function () {
        Livewire::actingAs($this->student, 'student')
            ->test('student.self-program')
            ->set("settleAmounts.{$this->item->id}", '')
            ->call('settle', $this->item->id)
            ->assertHasErrors("settleAmounts.{$this->item->id}");
    });
});

describe('the supervisor editor', function () {
    beforeEach(function () {
        $this->supervisor = Supervisor::factory()->create();
        $this->supervisor->stages()->attach($this->stage->id);
    });

    it('writes the five tracks of a week', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->call('openWeek', $this->week->id)
            ->set('rows.quran_wird.description', 'سورة الملك')
            ->set('rows.quran_wird.target_amount', 20)
            ->set('rows.masmou.target_amount', 4)
            ->set('rows.masmou.unit', 'درس')
            ->call('save')
            ->assertHasNoErrors();

        $items = $this->week->fresh('items')->items->keyBy(fn ($i) => $i->track->value);

        expect($items)->toHaveCount(5)
            ->and($items['quran_wird']->description)->toBe('سورة الملك')
            ->and((float) $items['quran_wird']->target_amount)->toBe(20.0)
            ->and((float) $items['masmou']->target_amount)->toBe(4.0);
    });

    it('keeps the wird in pages whatever unit is submitted', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->call('openWeek', $this->week->id)
            ->set('rows.quran_wird.unit', 'حديث')
            ->set('rows.quran_wird.target_amount', 10)
            ->call('save');

        expect($this->week->fresh('items')->items->firstWhere('track.key', SelfProgramTrack::QURAN_WIRD)->unit)
            ->toBe('صفحة');
    });

    it('refuses a week belonging to a stage the supervisor does not hold', function () {
        $otherWeek = SelfProgramWeek::create([
            'stage_id' => Stage::factory()->create()->id,
            'program_type' => SelfProgramWeek::TYPE_SELF,
            'week_number' => 1,
            'starts_on' => '2026-09-06',
            'ends_on' => '2026-09-12',
        ]);

        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->call('openWeek', $otherWeek->id)
            ->assertStatus(403);
    });

    it('renders its page', function () {
        $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.self-program-weeks'))
            ->assertOk()
            ->assertSee('البرنامج الذاتي')
            ->assertSee($this->stage->name);
    });

    it('adds the next week seven days on', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('newStartsOn', '2026-09-13')
            ->call('addWeek')
            ->assertHasNoErrors();

        $added = SelfProgramWeek::where('stage_id', $this->stage->id)->where('week_number', 2)->first();

        expect($added)->not->toBeNull()
            ->and($added->ends_on->toDateString())->toBe('2026-09-19')
            // A new week arrives holding all five tracks, ready to fill in.
            ->and($added->items()->count())->toBe(5);
    });
});
