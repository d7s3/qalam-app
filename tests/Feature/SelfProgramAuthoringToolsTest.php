<?php

use App\Models\Circle;
use App\Models\SelfProgramDayOverride;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Services\SelfProgramService;
use App\Services\SelfProgramYearBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * What the screen that writes the year needed: a copy that carries the whole
 * week, a list that says which weeks were written, a preview of what the
 * student will actually be asked, and a way in from the academy's own sheet.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programme->id);

    $this->source = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
        'merged_days' => [['2026-09-09', '2026-09-10']],
    ]);

    $this->target = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 2,
        'starts_on' => '2026-09-13',
        'ends_on' => '2026-09-19',
    ]);

    $this->item = SelfProgramItem::create([
        'self_program_week_id' => $this->source->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'description' => 'المتممة',
        'content_url' => 'https://example.org/mutammima',
        'target_amount' => 27,
        'unit' => 'بيت',
    ]);

    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'day_date' => '2026-09-06',
        'content' => 'الأبيات ١ - ٥',
        'amount' => 5,
    ]);
});

it('carries the link, the day plan and the merges when a week is copied', function () {
    // It carried the description, the amount and the unit, and silently left
    // the rest — so a year built by copying lost two thirds of the first week.
    app(SelfProgramYearBuilder::class)->copyAcross($this->source, collect([$this->target]));

    $copy = $this->target->items()->firstOrFail();

    expect($copy->content_url)->toBe('https://example.org/mutammima');

    // The day plan is written against real dates, so Sunday becomes Sunday.
    $day = SelfProgramDayOverride::where('self_program_item_id', $copy->id)->firstOrFail();

    expect($day->day_date->format('Y-m-d'))->toBe('2026-09-13');
    expect($day->content)->toBe('الأبيات ١ - ٥');
    expect((float) $day->amount)->toBe(5.0);

    expect($this->target->fresh()->merged_days)->toBe([['2026-09-16', '2026-09-17']]);
});

it('says which weeks were written and which were left empty', function () {
    $component = Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id);

    $state = $component->instance()->weekState;

    expect($state[$this->source->id]['filled'])->toBe(1);
    expect($state[$this->source->id]['days'])->toBe(1);

    // Generated and never filled.
    expect($state[$this->target->id]['filled'])->toBe(0);
});

it('shows the plan as the student will meet it, arithmetic included', function () {
    $preview = app(SelfProgramService::class)->plannedGrid($this->source->fresh('items'));

    $sunday = collect($preview['rows'])->first()['cells']['2026-09-06'];

    // Written by hand, so it is shown as written.
    expect($sunday['expected'])->toBe(5.0);
    expect($sunday['written'])->toBeTrue();

    // Left blank, so the arithmetic decides — and the author sees the number
    // rather than finding it out from a student's question.
    $eighth = collect($preview['rows'])->first()['cells']['2026-09-08'];

    expect($eighth['written'])->toBeFalse();
    expect($eighth['expected'])->toBeGreaterThan(0);
});

it('spreads a table pasted from a sheet across the grid', function () {
    $component = Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->call('openWeek', $this->source->id)
        ->set('pasted', "المحفوظ\tالأبيات ١ - ٥\t-\tالأبيات ٦ - ١٢")
        ->call('applyPaste');

    $grid = $component->instance()->grid;

    expect($grid['mahfoudh']['2026-09-06']['content'])->toBe('الأبيات ١ - ٥');

    // A dash is how the academy's own sheet writes "nothing today".
    expect($grid['mahfoudh']['2026-09-07']['content'])->toBe('');
    expect($grid['mahfoudh']['2026-09-08']['content'])->toBe('الأبيات ٦ - ١٢');
});

it('skips a pasted row naming no field it knows', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->call('openWeek', $this->source->id)
        ->set('pasted', "شيء لا نعرفه\tأ\tب")
        ->call('applyPaste');

    // Guessing which field an unnamed row meant would be worse than ignoring it.
    expect(SelfProgramDayOverride::where('content', 'أ')->exists())->toBeFalse();
});
