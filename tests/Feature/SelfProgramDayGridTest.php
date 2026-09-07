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
use App\Services\SelfProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The academy writes its week as a grid: Sunday carries one text and Tuesday
 * another, and Wednesday is empty on purpose. The amount answered "how much
 * today" and could not answer "what today".
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

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $this->item = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'description' => 'المتممة',
        'target_amount' => 27,
        'unit' => 'بيت',
    ]);
});

it('gives a day its own content, not only its share', function () {
    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'day_date' => '2026-09-06',
        'content' => 'متممة الآجرومية',
        'amount' => 5,
    ]);

    $content = app(SelfProgramService::class)->dailyContent($this->item, $this->student);

    expect($content['2026-09-06'])->toBe('متممة الآجرومية');
    expect($content)->not->toHaveKey('2026-09-08');
});

it('lets a cohort differ from the programme, and a student from his cohort', function () {
    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'day_date' => '2026-09-06',
        'content' => 'ما كتبه المشرف',
    ]);

    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'circle_id' => $this->cohort->id,
        'day_date' => '2026-09-06',
        'content' => 'ما كتبه المعلّم',
    ]);

    $service = app(SelfProgramService::class);

    expect($service->dailyContent($this->item, $this->student)['2026-09-06'])->toBe('ما كتبه المعلّم');

    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'student_id' => $this->student->id,
        'day_date' => '2026-09-06',
        'content' => 'ما خُصّ به الطالب',
    ]);

    expect($service->dailyContent($this->item, $this->student)['2026-09-06'])->toBe('ما خُصّ به الطالب');
});

it('keeps each level in its own row rather than overwriting', function () {
    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'day_date' => '2026-09-06',
        'content' => 'للبرنامج',
    ]);

    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'circle_id' => $this->cohort->id,
        'day_date' => '2026-09-06',
        'content' => 'للدفعة',
    ]);

    // Three scopes, three rows — the unique index holds because `scope_key`
    // stands in for the two nullable columns.
    expect(SelfProgramDayOverride::count())->toBe(2);
    expect(SelfProgramDayOverride::pluck('scope_key')->sort()->values()->all())
        ->toBe(['c:'.$this->cohort->id, 'w']);
});

it('builds the week as a grid the student can read', function () {
    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'day_date' => '2026-09-06',
        'content' => 'متممة الآجرومية',
        'amount' => 5,
    ]);

    StudentSelfProgramEntry::create([
        'student_id' => $this->student->id,
        'self_program_item_id' => $this->item->id,
        'entry_date' => '2026-09-06',
        'amount_done' => 3,
    ]);

    $grid = app(SelfProgramService::class)->weekGrid($this->student, $this->week);

    expect($grid['days'])->toContain('2026-09-06');
    expect($grid['rows'])->toHaveCount(1);

    $cell = $grid['rows'][0]['cells']['2026-09-06'];

    expect($cell['content'])->toBe('متممة الآجرومية');
    expect($cell['expected'])->toBe(5.0);
    expect($cell['done'])->toBe(3.0);

    expect($grid['rows'][0]['target'])->toBe(27.0);
    expect($grid['rows'][0]['done'])->toBe(3.0);
});

it('leaves the arithmetic in charge of a day nobody wrote', function () {
    // No row for the eighth, so its share is the remainder over the days left —
    // which is what the programme has always done and still does.
    $grid = app(SelfProgramService::class)->weekGrid($this->student, $this->week);

    expect($grid['rows'][0]['cells']['2026-09-08']['content'])->toBeNull();
    expect($grid['rows'][0]['cells']['2026-09-08']['expected'])->toBeGreaterThan(0);
});

it('lets the supervisor write the grid for the programme', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->call('openWeek', $this->week->id)
        ->set('grid.mahfoudh.2026-09-06.content', 'متممة الآجرومية')
        ->set('grid.mahfoudh.2026-09-06.amount', '5')
        ->call('saveGrid');

    $row = SelfProgramDayOverride::where('self_program_item_id', $this->item->id)->firstOrFail();

    expect($row->content)->toBe('متممة الآجرومية');
    expect((float) $row->amount)->toBe(5.0);
    expect($row->circle_id)->toBeNull();
    expect($row->scope_key)->toBe('w');
});

it('lets the teacher write it for his cohort alone', function () {
    Livewire::actingAs($this->teacher, 'teacher')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'teacher')
        ->call('chooseCohort', $this->cohort->id)
        ->call('openWeek', $this->week->id)
        ->set('grid.mahfoudh.2026-09-06.content', 'ما اختاره المعلّم')
        ->call('saveGrid');

    $row = SelfProgramDayOverride::where('self_program_item_id', $this->item->id)->firstOrFail();

    expect($row->circle_id)->toBe($this->cohort->id);
});

it('clears a cell emptied in both fields', function () {
    SelfProgramDayOverride::create([
        'self_program_item_id' => $this->item->id,
        'day_date' => '2026-09-06',
        'content' => 'سيُمحى',
    ]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->call('openWeek', $this->week->id)
        ->set('grid.mahfoudh.2026-09-06.content', '')
        ->set('grid.mahfoudh.2026-09-06.amount', '')
        ->call('saveGrid');

    // Absence is how a day says it holds nothing; the sheet has deliberate
    // blanks in it.
    expect(SelfProgramDayOverride::count())->toBe(0);
});

it('joins merged days into one column', function () {
    $this->week->update(['merged_days' => [['2026-09-09', '2026-09-10']]]);

    $columns = app(SelfProgramService::class)->dayColumns($this->week->fresh());

    $merged = collect($columns)->firstWhere('merged', true);

    expect($merged['days'])->toBe(['2026-09-09', '2026-09-10']);
    expect($merged['key'])->toBe('2026-09-09');

    // One column where there were two, and the rest untouched.
    expect(collect($columns)->pluck('key'))->not->toContain('2026-09-10');
});

it('asks one amount across a merged column and counts either day', function () {
    $this->week->update(['merged_days' => [['2026-09-09', '2026-09-10']]]);

    StudentSelfProgramEntry::create([
        'student_id' => $this->student->id,
        'self_program_item_id' => $this->item->id,
        'entry_date' => '2026-09-10',
        'amount_done' => 4,
    ]);

    $grid = app(SelfProgramService::class)->weekGrid($this->student, $this->week->fresh());
    $cell = $grid['rows'][0]['cells']['2026-09-09'];

    expect($cell['merged'])->toBeTrue();
    expect($cell['done'])->toBe(4.0);
});

it('reads the wider scales by period rather than by day', function () {
    StudentSelfProgramEntry::create([
        'student_id' => $this->student->id,
        'self_program_item_id' => $this->item->id,
        'entry_date' => '2026-09-08',
        'amount_done' => 9,
    ]);

    $wide = app(SelfProgramService::class)
        ->periodGrid($this->student, '2026-09-01', '2026-09-30', 'week');

    expect($wide['columns'])->toHaveCount(1);
    expect($wide['rows'])->toHaveCount(1);

    $cell = $wide['rows'][0]['cells'][$wide['columns'][0]['key']];

    expect($cell['target'])->toBe(27.0);
    expect($cell['done'])->toBe(9.0);
});

it('lets the supervisor merge and unmerge from the grid', function () {
    $component = Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->call('openWeek', $this->week->id)
        ->set('selectedDays', ['2026-09-09', '2026-09-10'])
        ->call('mergeSelected');

    expect($this->week->fresh()->merged_days)->toBe([['2026-09-09', '2026-09-10']]);

    $component->call('unmerge', '2026-09-09');

    expect($this->week->fresh()->merged_days)->toBe([]);
});

it('refuses a merge of fewer than two days', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('asRole', 'supervisor')
        ->set('stageId', $this->programme->id)
        ->call('openWeek', $this->week->id)
        ->set('selectedDays', ['2026-09-09'])
        ->call('mergeSelected');

    expect($this->week->fresh()->merged_days)->toBeNull();
});
