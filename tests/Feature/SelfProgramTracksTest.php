<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramTrackExclusion;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The programme was defined as five fields fixed in code. The academy decided
 * otherwise: a month may run on three, a sixth may be wanted, one may be set
 * aside for a term without the weeks that used it losing their meaning.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->other = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);
    $this->manager = Manager::factory()->create();
});

it('still knows the five it began with', function () {
    expect(SelfProgramTrack::ordered()->pluck('key')->all())
        ->toBe(['quran_wird', 'maqrou', 'masmou', 'tahdheer', 'mahfoudh']);

    // Their keys are unchanged, so every week ever written still reads.
    expect(SelfProgramTrack::quranWird()?->fixedUnit())->toBe('صفحة');
});

it('takes a sixth field nobody had thought of', function () {
    Livewire::actingAs($this->manager, 'manager')
        ->test('shared.self-program-tracks')
        ->set('asRole', 'manager')
        ->set('newLabel', 'السيرة')
        ->set('newUnit', 'درس')
        ->call('addTrack')
        ->assertHasNoErrors();

    $added = SelfProgramTrack::where('label', 'السيرة')->firstOrFail();

    expect($added->is_system)->toBeFalse();
    expect($added->defaultUnit())->toBe('درس');
    expect(SelfProgramTrack::ordered())->toHaveCount(6);
});

it('sets a field aside for a term and brings it back on its own', function () {
    $masmou = SelfProgramTrack::findByKey('masmou');

    SelfProgramTrackExclusion::create([
        'self_program_track_id' => $masmou->id,
        'stage_id' => $this->programme->id,
        'starts_on' => '2026-09-01',
        'ends_on' => '2026-09-30',
    ]);

    // Inside the term it is not asked for.
    expect(SelfProgramTrack::orderedFor($this->programme->id, '2026-09-15')->pluck('key'))
        ->not->toContain('masmou');

    // Either side of it, nobody had to remember to restore anything.
    expect(SelfProgramTrack::orderedFor($this->programme->id, '2026-10-01')->pluck('key'))
        ->toContain('masmou');

    // And another programme is untouched.
    expect(SelfProgramTrack::orderedFor($this->other->id, '2026-09-15')->pluck('key'))
        ->toContain('masmou');
});

it('retires a field indefinitely when no dates are given', function () {
    SelfProgramTrackExclusion::create([
        'self_program_track_id' => SelfProgramTrack::findByKey('tahdheer')->id,
    ]);

    expect(SelfProgramTrack::orderedFor(null, '2030-01-01')->pluck('key'))->not->toContain('tahdheer');
});

it('does not create a week around a field that is set aside', function () {
    SelfProgramTrackExclusion::create([
        'self_program_track_id' => SelfProgramTrack::findByKey('masmou')->id,
        'stage_id' => $this->programme->id,
    ]);

    $week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohort->id,
        'program_type' => 'self',
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $week->ensureAllTracks();

    // Four, not five — and none of them empty and shown as unmet.
    expect($week->items()->count())->toBe(4);
    expect($week->items()->pluck('track'))->not->toContain('masmou');
});

it('leaves a week already written alone when a field is set aside after it', function () {
    $week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohort->id,
        'program_type' => 'self',
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $item = SelfProgramItem::create([
        'self_program_week_id' => $week->id,
        'track' => 'masmou',
        'target_amount' => 4,
        'unit' => 'درس',
    ]);

    SelfProgramTrackExclusion::create([
        'self_program_track_id' => SelfProgramTrack::findByKey('masmou')->id,
    ]);

    // The row stands and still knows what it is; setting a field aside is not
    // deleting the history of it.
    expect($item->fresh()->track?->label())->toBe('المسموع');
});

it('brings a field back', function () {
    $exclusion = SelfProgramTrackExclusion::create([
        'self_program_track_id' => SelfProgramTrack::findByKey('maqrou')->id,
    ]);

    Livewire::actingAs($this->manager, 'manager')
        ->test('shared.self-program-tracks')
        ->set('asRole', 'manager')
        ->call('restore', $exclusion->id);

    expect(SelfProgramTrack::orderedFor()->pluck('key'))->toContain('maqrou');
});
