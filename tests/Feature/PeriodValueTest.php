<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\PeriodValue;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->other = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->student = Student::factory()->create([
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
    ]);
});

it('runs on the first and last day of its own stretch', function () {
    // The cast writes `Y-m-d H:i:s`, so a value compared as text would begin a
    // day late and end a day early — invisible on exactly the days it opens
    // and closes.
    PeriodValue::create([
        'title' => 'الصدق',
        'practice' => 'لا يمرّ يومٌ بكذبة، ولو مازحاً.',
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    expect(PeriodValue::runningOn('2026-09-06', null, null)->count())->toBe(1);
    expect(PeriodValue::runningOn('2026-09-12', null, null)->count())->toBe(1);
    expect(PeriodValue::runningOn('2026-09-13', null, null)->count())->toBe(0);
});

it('reaches the whole academy when it names nobody', function () {
    PeriodValue::create(['title' => 'الأمانة', 'starts_on' => '2026-09-06', 'ends_on' => '2026-09-12']);

    expect(PeriodValue::runningOn('2026-09-08', $this->programme->id, $this->cohort->id)->count())->toBe(1);
});

it('keeps another programme value to itself', function () {
    PeriodValue::create([
        'title' => 'برّ الوالدين',
        'stage_id' => $this->other->id,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    expect(PeriodValue::runningOn('2026-09-08', $this->programme->id, $this->cohort->id)->count())->toBe(0);
    expect(PeriodValue::runningOn('2026-09-08', $this->other->id, null)->count())->toBe(1);
});

it('puts what we are working on in front of the student', function () {
    PeriodValue::create([
        'title' => 'الصدق',
        'practice' => 'لا يمرّ يومٌ بكذبة، ولو مازحاً.',
        'stage_id' => $this->programme->id,
        'starts_on' => now()->subDay()->format('Y-m-d'),
        'ends_on' => now()->addDays(5)->format('Y-m-d'),
    ]);

    Livewire::actingAs($this->student, 'student')
        ->test('shared.my-day')
        ->set('asRole', 'student')
        ->assertSee('ما نعمل عليه')
        ->assertSee('الصدق')
        ->assertSee('ولو مازحاً');
});

it('lets a supervisor write a value for his own programme', function () {
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($this->programme->id);

    Livewire::actingAs($supervisor, 'supervisor')
        ->test('shared.period-values')
        ->set('asRole', 'supervisor')
        ->set('title', 'الأمانة')
        ->set('practice', 'ما استُودعت شيئاً إلا رددته.')
        ->set('stageId', $this->programme->id)
        ->set('startsOn', '2026-09-06')
        ->set('endsOn', '2026-09-12')
        ->call('save')
        ->assertHasNoErrors();

    $value = PeriodValue::firstOrFail();

    expect($value->title)->toBe('الأمانة');
    expect($value->stage_id)->toBe($this->programme->id);
    expect($value->created_by_id)->toBe($supervisor->id);
});

it('refuses a value written for a programme he does not hold', function () {
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($this->programme->id);

    Livewire::actingAs($supervisor, 'supervisor')
        ->test('shared.period-values')
        ->set('asRole', 'supervisor')
        ->set('title', 'ليست له')
        ->set('stageId', $this->other->id)
        ->set('startsOn', '2026-09-06')
        ->set('endsOn', '2026-09-12')
        ->call('save')
        ->assertStatus(403);

    expect(PeriodValue::count())->toBe(0);
});

it('lays out a run of weeks to be filled in', function () {
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($this->programme->id);

    Livewire::actingAs($supervisor, 'supervisor')
        ->test('shared.period-values')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->set('generateFrom', '2026-09-06')
        ->set('generateWeeks', 4)
        ->call('generate');

    expect(PeriodValue::count())->toBe(4);

    // Consecutive and a week each, so a term is edited rather than typed.
    $weeks = PeriodValue::orderBy('starts_on')->get();

    expect($weeks->first()->starts_on->format('Y-m-d'))->toBe('2026-09-06');
    expect($weeks->first()->ends_on->format('Y-m-d'))->toBe('2026-09-12');
    expect($weeks->last()->starts_on->format('Y-m-d'))->toBe('2026-09-27');
});

it('refuses to end a value before it begins', function () {
    Livewire::actingAs(Manager::factory()->create(), 'manager')
        ->test('shared.period-values')
        ->set('asRole', 'manager')
        ->set('title', 'مقلوبة')
        ->set('startsOn', '2026-09-12')
        ->set('endsOn', '2026-09-06')
        ->call('save')
        ->assertHasErrors('endsOn');

    expect(PeriodValue::count())->toBe(0);
});
