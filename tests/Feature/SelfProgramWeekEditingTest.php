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
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Moving and removing a week of the self programme.
 *
 * A week could be added and written, and then nothing on the screen could touch
 * it again: a programme that began a week later than planned had to be deleted
 * — except that deleting it was not offered either — and retyped.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programme->id);

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
        'merged_days' => [['2026-09-09', '2026-09-10']],
    ]);

    $this->item = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'description' => 'المتممة',
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

function weeksScreen($actor, string $role, int $stageId, int $weekId)
{
    return Livewire::actingAs($actor, $role)
        ->test('supervisor.self-program-weeks')
        ->set('asRole', $role)
        ->set('stageId', $stageId)
        ->call('openWeek', $weekId);
}

it('moves the week and carries its written days with it', function () {
    weeksScreen($this->supervisor, 'supervisor', $this->programme->id, $this->week->id)
        ->call('editDates')
        ->set('editStartsOn', '2026-09-13')
        ->call('saveDates');

    $week = $this->week->fresh();

    expect($week->starts_on->format('Y-m-d'))->toBe('2026-09-13');
    expect($week->ends_on->format('Y-m-d'))->toBe('2026-09-19');

    // The day plan is written against real dates, so a week moved without it
    // would arrive empty and strand its content on days nobody reads.
    $day = SelfProgramDayOverride::where('self_program_item_id', $this->item->id)->firstOrFail();

    expect($day->day_date->format('Y-m-d'))->toBe('2026-09-13');
    expect($day->content)->toBe('الأبيات ١ - ٥');

    // And the days it joins into one column travel with it.
    expect($week->merged_days)->toBe([['2026-09-16', '2026-09-17']]);
});

it('leaves where a student was standing alone when the plan moves', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohort->id, 'stage_id' => $this->programme->id]);

    StudentSelfProgramEntry::create([
        'student_id' => $student->id,
        'self_program_item_id' => $this->item->id,
        'entry_date' => '2026-09-08',
        'amount_done' => 4,
        'source' => 'student',
    ]);

    weeksScreen($this->supervisor, 'supervisor', $this->programme->id, $this->week->id)
        ->call('editDates')
        ->set('editStartsOn', '2026-09-13')
        ->call('saveDates');

    // An entry says what a boy did on a Tuesday. Moving the plan does not move
    // his Tuesday.
    expect(StudentSelfProgramEntry::firstOrFail()->entry_date->format('Y-m-d'))->toBe('2026-09-08');
});

it('refuses to move a week onto days another week already covers', function () {
    SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 2,
        'starts_on' => '2026-09-13',
        'ends_on' => '2026-09-19',
    ]);

    weeksScreen($this->supervisor, 'supervisor', $this->programme->id, $this->week->id)
        ->call('editDates')
        ->set('editStartsOn', '2026-09-13')
        ->call('saveDates');

    // Two weeks over the same days leave «which week is mine» answered by
    // whichever the database returns first.
    expect($this->week->fresh()->starts_on->format('Y-m-d'))->toBe('2026-09-06');
});

it('counts what a deletion would cost before it happens', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohort->id, 'stage_id' => $this->programme->id]);

    foreach (['2026-09-07', '2026-09-08'] as $on) {
        StudentSelfProgramEntry::create([
            'student_id' => $student->id,
            'self_program_item_id' => $this->item->id,
            'entry_date' => $on,
            'amount_done' => 3,
            'source' => 'student',
        ]);
    }

    $screen = weeksScreen($this->supervisor, 'supervisor', $this->programme->id, $this->week->id);

    expect($screen->instance()->deleteCost)->toBe(2);
});

it('deletes the week and everything hanging off it', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohort->id, 'stage_id' => $this->programme->id]);

    StudentSelfProgramEntry::create([
        'student_id' => $student->id,
        'self_program_item_id' => $this->item->id,
        'entry_date' => '2026-09-07',
        'amount_done' => 3,
        'source' => 'student',
    ]);

    weeksScreen($this->supervisor, 'supervisor', $this->programme->id, $this->week->id)
        ->call('deleteWeek');

    expect(SelfProgramWeek::find($this->week->id))->toBeNull();
    expect(SelfProgramItem::count())->toBe(0);
    expect(StudentSelfProgramEntry::count())->toBe(0);
    expect(SelfProgramDayOverride::count())->toBe(0);
});

it('refuses a week outside the reader’s reach', function () {
    $other = Stage::factory()->create();

    $elsewhere = SelfProgramWeek::create([
        'stage_id' => $other->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->set('weekId', $elsewhere->id)
        ->call('deleteWeek')
        ->assertForbidden();

    expect(SelfProgramWeek::find($elsewhere->id))->not->toBeNull();
});

it('lets the teacher move and remove his own cohort’s week', function () {
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($this->cohort->id);

    $own = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohort->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-10-04',
        'ends_on' => '2026-10-10',
    ]);

    $screen = Livewire::actingAs($teacher, 'teacher')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'teacher')
        ->set('circleId', $this->cohort->id)
        ->set('stageId', $this->programme->id)
        ->call('openWeek', $own->id)
        ->call('editDates')
        ->set('editStartsOn', '2026-10-11')
        ->call('saveDates');

    expect($own->fresh()->starts_on->format('Y-m-d'))->toBe('2026-10-11');

    $screen->call('deleteWeek');

    expect(SelfProgramWeek::find($own->id))->toBeNull();
});
