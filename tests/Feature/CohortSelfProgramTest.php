<?php

use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Services\SelfProgramService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The supervisor writes the programme and it reaches every cohort in it. A
 * teacher writes for his own, and where both cover a day his students read his
 * — it is the more particular of the two, and it is the one their own teacher
 * wrote.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();

    $this->mine = Circle::factory()->create(['stage_id' => $this->programme->id, 'name' => 'دفعتي']);
    $this->theirs = Circle::factory()->create(['stage_id' => $this->programme->id, 'name' => 'دفعة غيري']);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->mine->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programme->id);

    $this->student = Student::factory()->create(['circle_id' => $this->mine->id, 'stage_id' => $this->programme->id]);
    $this->other = Student::factory()->create(['circle_id' => $this->theirs->id, 'stage_id' => $this->programme->id]);
});

/** A week covering today, for a programme or for one cohort inside it. */
function weekFor(int $stageId, ?int $circleId, int $number, float $target): SelfProgramWeek
{
    $week = SelfProgramWeek::create([
        'stage_id' => $stageId,
        'circle_id' => $circleId,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => $number,
        'starts_on' => now()->subDays(2)->format('Y-m-d'),
        'ends_on' => now()->addDays(4)->format('Y-m-d'),
    ]);

    SelfProgramItem::create([
        'self_program_week_id' => $week->id,
        'track' => SelfProgramTrack::MAQROU,
        'target_amount' => $target,
        'unit' => 'صفحة',
    ]);

    return $week;
}

it('gives a cohort its own teacher week over the programme one', function () {
    weekFor($this->programme->id, null, 1, 20);
    weekFor($this->programme->id, $this->mine->id, 1, 35);

    $service = app(SelfProgramService::class);

    expect((float) $service->currentWeek($this->student)->items->first()->target_amount)->toBe(35.0);

    // The cohort next door, whose teacher wrote nothing, reads the programme's.
    expect((float) $service->currentWeek($this->other)->items->first()->target_amount)->toBe(20.0);
});

it('falls back to the programme when the teacher wrote nothing', function () {
    weekFor($this->programme->id, null, 1, 20);

    expect((float) app(SelfProgramService::class)->currentWeek($this->student)->items->first()->target_amount)
        ->toBe(20.0);
});

it('lets a cohort week and a programme week share a number', function () {
    // They did not, and a teacher's first week collided with the supervisor's.
    weekFor($this->programme->id, null, 1, 20);
    weekFor($this->programme->id, $this->mine->id, 1, 35);

    expect(SelfProgramWeek::self()->count())->toBe(2);
});

it('still refuses two programme weeks with one number', function () {
    // A nullable column in a unique index rejects nothing — SQL counts two
    // NULLs as different — so the programme-wide weeks are guarded by an index
    // of their own.
    weekFor($this->programme->id, null, 1, 20);

    expect(fn () => weekFor($this->programme->id, null, 1, 25))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('writes a teacher week against his cohort', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'teacher')
        ->call('chooseCohort', $this->mine->id)
        ->set('newStartsOn', '2026-11-01')
        ->call('addWeek')
        ->assertHasNoErrors();

    $week = SelfProgramWeek::whereDate('starts_on', '2026-11-01')->firstOrFail();

    expect($week->circle_id)->toBe($this->mine->id);
    expect($week->stage_id)->toBe($this->programme->id);
});

it('refuses a cohort he does not teach', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'teacher')
        ->call('chooseCohort', $this->theirs->id)
        ->assertStatus(403);
});

it('keeps the supervisor writing for the programme', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->set('newStartsOn', '2026-11-08')
        ->call('addWeek');

    expect(SelfProgramWeek::whereDate('starts_on', '2026-11-08')->firstOrFail()->circle_id)->toBeNull();
});

it('shows each writer only the weeks he wrote for', function () {
    weekFor($this->programme->id, null, 1, 20);
    weekFor($this->programme->id, $this->mine->id, 1, 35);

    $teacherSees = Livewire::actingAs($this->teacher, 'teacher')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'teacher')
        ->call('chooseCohort', $this->mine->id)
        ->instance()->weeks;

    $supervisorSees = Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->instance()->weeks;

    expect($teacherSees->pluck('circle_id')->all())->toBe([$this->mine->id]);
    expect($supervisorSees->pluck('circle_id')->all())->toBe([null]);
});
