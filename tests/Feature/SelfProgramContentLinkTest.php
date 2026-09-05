<?php

use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A week that says "منظومة البيقونية، عشرة أبيات" leaves the student to find
 * the poem. The supervisor has it in front of him while he writes the week.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->student = Student::factory()->create([
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
    ]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programme->id);

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohort->id,
        'program_type' => 'self',
        'week_number' => 1,
        'starts_on' => now()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d'),
        'ends_on' => now()->startOfWeek(Carbon::SUNDAY)->addDays(6)->format('Y-m-d'),
    ]);
});

it('carries a link to the thing itself', function () {
    $item = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'description' => 'منظومة البيقونية',
        'content_url' => 'https://example.org/bayquniyyah',
        'target_amount' => 10,
        'unit' => 'بيت',
    ]);

    expect($item->carriesContentLink())->toBeTrue();
    expect($item->contentLinkLabel())->toBe('افتح المحتوى');
});

it('leaves the Quran wird without one', function () {
    $item = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::QURAN_WIRD,
        'target_amount' => 2,
    ]);

    // The mushaf is in the application; a link out of it is a step backwards.
    expect($item->carriesContentLink())->toBeFalse();
});

it('puts the link in front of the student', function () {
    SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'description' => 'منظومة البيقونية',
        'content_url' => 'https://example.org/bayquniyyah',
        'target_amount' => 10,
        'unit' => 'بيت',
    ]);

    Livewire::actingAs($this->student, 'student')
        ->test('student.self-program')
        ->assertSee('منظومة البيقونية')
        ->assertSee('https://example.org/bayquniyyah', false)
        ->assertSee('افتح المحتوى');
});

it('refuses something that is not a link', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->call('openWeek', $this->week->id)
        ->set('rows.mahfoudh.content_url', 'ليس رابطاً')
        ->call('save')
        ->assertHasErrors('rows.mahfoudh.content_url');
});

it('never writes a link onto the wird even when one is sent', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->call('openWeek', $this->week->id)
        ->set('rows.quran_wird.content_url', 'https://example.org/mushaf')
        ->set('rows.quran_wird.target_amount', 2)
        ->call('save');

    $wird = SelfProgramItem::where('self_program_week_id', $this->week->id)
        ->where('track', SelfProgramTrack::QURAN_WIRD)
        ->first();

    expect($wird?->content_url)->toBeNull();
});
