<?php

use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentSelfProgramEntry;
use App\Models\Teacher;
use App\Services\SelfProgramService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * What a student did, week by week, and what of it came late.
 *
 * The programme already let him settle an old week's work — recorded under
 * today, because that is when it was done, while still counting towards the
 * week it belonged to. What it never did was say so: a boy a fortnight behind
 * and a boy who kept up read identically.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-20 09:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'stage_id' => $this->stage->id,
    ]);

    $this->past = SelfProgramWeek::create([
        'stage_id' => $this->stage->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $this->now = SelfProgramWeek::create([
        'stage_id' => $this->stage->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 2,
        'starts_on' => '2026-09-20',
        'ends_on' => '2026-09-26',
    ]);

    $this->pastItem = SelfProgramItem::create([
        'self_program_week_id' => $this->past->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'target_amount' => 10,
        'unit' => 'بيت',
    ]);

    $this->nowItem = SelfProgramItem::create([
        'self_program_week_id' => $this->now->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'target_amount' => 10,
        'unit' => 'بيت',
    ]);

    $this->service = app(SelfProgramService::class);
});

function put(int $itemId, int $studentId, float $amount, string $on): void
{
    StudentSelfProgramEntry::create([
        'student_id' => $studentId,
        'self_program_item_id' => $itemId,
        'entry_date' => $on,
        'amount_done' => $amount,
        'source' => StudentSelfProgramEntry::SOURCE_STUDENT,
    ]);
}

it('separates what was done in its week from what came after it', function () {
    put($this->pastItem->id, $this->student->id, 4, '2026-09-08');   // in time
    put($this->pastItem->id, $this->student->id, 6, '2026-09-19');   // a week late

    $record = collect($this->service->weeklyRecord($this->student))->keyBy(fn ($row) => $row['week']->id);
    $week = $record[$this->past->id];

    // The whole ten still counts towards the week it belonged to.
    expect($week['tracks'][0]['done'])->toBe(10.0);
    expect($week['overall'])->toBe(100.0);

    // And six of them are said to be late rather than quietly folded in.
    expect($week['tracks'][0]['late'])->toBe(6.0);
    expect($week['late'])->toBe(6.0);
});

it('calls nothing late in a week that has not closed', function () {
    put($this->nowItem->id, $this->student->id, 3, '2026-09-20');

    $record = collect($this->service->weeklyRecord($this->student))->keyBy(fn ($row) => $row['week']->id);

    expect($record[$this->now->id]['late'])->toBe(0.0);
    expect($record[$this->now->id]['closed'])->toBeFalse();
    expect($record[$this->past->id]['closed'])->toBeTrue();
});

it('counts the closing day itself as in time', function () {
    // A boy who finished on the last evening of his week was not late.
    put($this->pastItem->id, $this->student->id, 10, '2026-09-12');

    $record = collect($this->service->weeklyRecord($this->student))->keyBy(fn ($row) => $row['week']->id);

    expect($record[$this->past->id]['late'])->toBe(0.0);
});

it('reads the weeks in order, and each of them', function () {
    $record = $this->service->weeklyRecord($this->student);

    expect($record)->toHaveCount(2);
    expect($record[0]['week']->week_number)->toBe(1);
    expect($record[1]['week']->week_number)->toBe(2);
});

it('leaves another student’s work out of it', function () {
    $other = Student::factory()->create(['circle_id' => $this->circle->id, 'stage_id' => $this->stage->id]);

    put($this->pastItem->id, $other->id, 10, '2026-09-08');

    $record = collect($this->service->weeklyRecord($this->student))->keyBy(fn ($row) => $row['week']->id);

    expect($record[$this->past->id]['tracks'][0]['done'])->toBe(0.0);
});

it('shows the student his own record, and says what was late', function () {
    put($this->pastItem->id, $this->student->id, 4, '2026-09-08');
    put($this->pastItem->id, $this->student->id, 6, '2026-09-19');

    Livewire\Livewire::actingAs($this->student, 'student')
        ->test('shared.self-program-record', ['role' => 'student'])
        ->assertOk()
        ->assertSee('أُنجز متأخّراً')
        ->assertSee('منه متأخّر:');
});

it('does not open the entries underneath to the student himself', function () {
    put($this->pastItem->id, $this->student->id, 4, '2026-09-08');

    $screen = Livewire\Livewire::actingAs($this->student, 'student')
        ->test('shared.self-program-record', ['role' => 'student']);

    expect($screen->instance()->detailed())->toBeFalse();
    $screen->assertDontSee('التفاصيل');
});

it('opens the entries to the teacher, each with the hand that wrote it', function () {
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($this->circle->id);

    StudentSelfProgramEntry::create([
        'student_id' => $this->student->id,
        'self_program_item_id' => $this->pastItem->id,
        'entry_date' => '2026-09-19',
        'amount_done' => 6,
        'source' => StudentSelfProgramEntry::SOURCE_STUDENT,
        'recorded_by_type' => $teacher->getMorphClass(),
        'recorded_by_id' => $teacher->id,
    ]);

    Livewire\Livewire::actingAs($teacher, 'teacher')
        ->test('shared.self-program-record', ['role' => 'teacher'])
        ->set('studentId', $this->student->id)
        ->call('openWeek', $this->past->id)
        ->assertOk()
        ->assertSee('متأخّر')
        ->assertSee($teacher->name);
});

it('refuses a student the teacher does not teach', function () {
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($this->circle->id);

    $elsewhere = Student::factory()->create([
        'circle_id' => Circle::factory()->create(['stage_id' => $this->stage->id])->id,
    ]);

    Livewire\Livewire::actingAs($teacher, 'teacher')
        ->test('shared.self-program-record', ['role' => 'teacher'])
        ->set('studentId', $elsewhere->id)
        ->assertForbidden();
});
