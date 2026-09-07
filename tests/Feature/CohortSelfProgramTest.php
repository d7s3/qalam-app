<?php

use App\Models\Circle;
use App\Models\Manager;
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

/**
 * The manager and the administrator write the programme through the very same
 * screen the supervisor does — three routes, one component — so the units have
 * to hold for them without anything being repeated for each office.
 */
describe('the offices above', function () {
    it('lets the manager write a week in the units of its fields', function () {
        $week = weekFor($this->programme->id, null, 1, 20);

        Livewire::actingAs(Manager::factory()->create(), 'manager')
            ->test('supervisor.self-program-weeks')
            ->set('asRole', 'manager')
            ->call('openWeek', $week->id)
            ->set('rows.masmou.hours', 2)
            ->set('rows.masmou.minutes', 30)
            ->set('rows.mahfoudh.unit', 'حديث')
            ->set('rows.mahfoudh.target_amount', 4.5)
            ->call('save')
            ->assertHasNoErrors();

        $items = $week->fresh('items')->items->keyBy(fn ($i) => $i->track->value);

        expect((float) $items['masmou']->target_amount)->toBe(150.0)
            ->and($items['masmou']->unit)->toBe('دقيقة')
            ->and((float) $items['mahfoudh']->target_amount)->toBe(5.0)
            ->and($items['mahfoudh']->unit)->toBe('حديث');
    });

    it('holds the administrator to them too', function () {
        $week = weekFor($this->programme->id, null, 2, 20);

        Livewire::actingAs(Manager::factory()->create(['is_super_admin' => true]), 'manager')
            ->test('supervisor.self-program-weeks')
            ->set('asRole', 'manager')
            ->call('openWeek', $week->id)
            ->set('rows.maqrou.target_amount', 7.3)
            ->set('rows.tahdheer.unit', 'دقيقة')
            ->set('rows.tahdheer.minutes', 45)
            ->call('save')
            ->assertHasNoErrors();

        $items = $week->fresh('items')->items->keyBy(fn ($i) => $i->track->value);

        // Seeing everything is not the same as being able to write anything:
        // pages are halves at most, and التحضير by listening is a length of time.
        expect((float) $items['maqrou']->target_amount)->toBe(7.5)
            ->and((float) $items['tahdheer']->target_amount)->toBe(45.0)
            ->and($items['tahdheer']->unit)->toBe('دقيقة');
    });
});

/**
 * A sixth field the academy adds is fitted the same way the five are, so the
 * rule does not stop at what was seeded.
 */
it('fits a field the academy adds to the same vocabulary', function () {
    Livewire::actingAs(Manager::factory()->create(), 'manager')
        ->test('shared.self-program-tracks')
        ->set('asRole', 'manager')
        ->set('newLabel', 'الاستماع للسيرة')
        ->set('newUnit', 'دقيقة')
        ->call('addTrack')
        ->assertHasNoErrors();

    $added = SelfProgramTrack::where('label', 'الاستماع للسيرة')->firstOrFail();

    expect($added->isDuration())->toBeTrue()
        ->and($added->fixedUnit())->toBe('دقيقة')
        ->and($added->unitOptions())->toBe(['دقيقة' => 'دقائق']);
});

it('leaves a field counted in the academy\'s own word alone', function () {
    Livewire::actingAs(Manager::factory()->create(), 'manager')
        ->test('shared.self-program-tracks')
        ->set('asRole', 'manager')
        ->set('newLabel', 'المجالس')
        ->set('newUnit', 'مجلس')
        ->call('addTrack')
        ->assertHasNoErrors();

    $added = SelfProgramTrack::where('label', 'المجالس')->firstOrFail();

    // Unknown to the vocabulary, so counted plainly rather than guessed at.
    expect($added->unitOptions())->toBe([])
        ->and($added->defaultUnit())->toBe('مجلس')
        ->and($added->isDuration())->toBeFalse()
        ->and($added->unitFor('أي شيء'))->toBe('أي شيء');
});
