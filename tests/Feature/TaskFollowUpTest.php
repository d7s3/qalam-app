<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskSeries;
use App\Models\TaskTemplate;
use App\Models\Teacher;
use App\Services\TaskFollowUpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * What turns a list of tasks into work being followed.
 *
 * The academy had tasks with a title, an owner and a date. Everything here is
 * about the difference between that and knowing where the work stands: a
 * pattern that raises itself, an office asked as one man but answerable as six,
 * a set raised whole from a template, a slip that reaches somebody who can act,
 * and a record of every hand that touched any of it.
 */
beforeEach(function () {
    $this->service = app(TaskFollowUpService::class);

    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->teacher = Teacher::factory()->create(['name' => 'معلم الدفعة', 'is_approved' => true]);
    $this->teacher->circles()->attach($this->cohort->id);

    $this->otherTeacher = Teacher::factory()->create(['name' => 'معلم آخر', 'is_approved' => true]);
    $this->otherTeacher->circles()->attach($this->cohort->id);

    $this->supervisor = Supervisor::factory()->create(['name' => 'مشرف البرنامج', 'is_approved' => true]);
    $this->supervisor->stages()->attach($this->programme->id);

    $this->manager = Manager::factory()->create(['is_approved' => true]);
});

describe('a pattern that raises itself', function () {
    it('raises the weekly task on its day and on no other', function () {
        $thursday = Carbon::parse('2026-09-10'); // خميس

        TaskSeries::create([
            'title' => 'التقرير الأسبوعي',
            'assigned_to_type' => 'teacher',
            'assigned_to_id' => $this->teacher->id,
            'every' => TaskSeries::WEEKLY,
            'on_weekday' => 5, // الخميس، والأحد واحد
            'created_by_type' => 'manager',
            'created_by_id' => $this->manager->id,
            'starts_on' => '2026-09-01',
        ]);

        expect($this->service->raiseDue($thursday))->toBe(1)
            ->and($this->service->raiseDue($thursday->copy()->addDay()))->toBe(0)
            ->and(Task::where('title', 'التقرير الأسبوعي')->count())->toBe(1);
    });

    it('raises one day\'s work however many times it is run', function () {
        $thursday = Carbon::parse('2026-09-10');

        TaskSeries::create([
            'title' => 'التقرير الأسبوعي',
            'assigned_to_type' => 'teacher',
            'assigned_to_id' => $this->teacher->id,
            'every' => TaskSeries::WEEKLY,
            'on_weekday' => 5,
            'created_by_type' => 'manager',
            'created_by_id' => $this->manager->id,
            'starts_on' => '2026-09-01',
        ]);

        // A scheduler that missed a night is caught up by running it again, so
        // running it twice must not double the work.
        $this->service->raiseDue($thursday);
        $this->service->raiseDue($thursday);
        $this->service->raiseDue($thursday);

        expect(Task::count())->toBe(1);
    });

    it('stops when the pattern is switched off or has run out', function () {
        $series = TaskSeries::create([
            'title' => 'يومية',
            'assigned_to_type' => 'teacher',
            'assigned_to_id' => $this->teacher->id,
            'every' => TaskSeries::DAILY,
            'created_by_type' => 'manager',
            'created_by_id' => $this->manager->id,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-05',
        ]);

        expect($this->service->raiseDue(Carbon::parse('2026-09-03')))->toBe(1)
            // Past its end.
            ->and($this->service->raiseDue(Carbon::parse('2026-09-06')))->toBe(0);

        $series->update(['is_active' => false, 'ends_on' => null]);

        expect($this->service->raiseDue(Carbon::parse('2026-09-07')))->toBe(0);
    });
});

describe('an office asked as one, answerable as many', function () {
    it('gives every teacher of a programme his own task, under one key', function () {
        TaskSeries::create([
            'title' => 'سلّم كشف الحضور',
            'assign_to_role' => 'teacher',
            'assign_scope_type' => 'stages',
            'assign_scope_ids' => [$this->programme->id],
            'every' => TaskSeries::DAILY,
            'created_by_type' => 'manager',
            'created_by_id' => $this->manager->id,
            'starts_on' => '2026-09-01',
        ]);

        $this->service->raiseDue(Carbon::parse('2026-09-02'));

        $raised = Task::where('title', 'سلّم كشف الحضور')->get();

        // One task each — six teachers sharing one task is one task nobody
        // finished — and one key between them, so the asking was one act.
        expect($raised)->toHaveCount(2)
            ->and($raised->pluck('assigned_to_id')->all())
            ->toEqualCanonicalizing([$this->teacher->id, $this->otherTeacher->id])
            ->and($raised->pluck('batch_key')->unique())->toHaveCount(1);
    });

    it('leaves out the teachers of another programme', function () {
        $far = Stage::factory()->create();
        $farCohort = Circle::factory()->create(['stage_id' => $far->id]);
        $farTeacher = Teacher::factory()->create(['name' => 'معلم بعيد', 'is_approved' => true]);
        $farTeacher->circles()->attach($farCohort->id);

        $made = $this->service->raiseForEach(
            ['title' => 'مهمة', 'status' => 'pending', 'due_date' => '2026-09-02',
                'created_by_type' => 'manager', 'created_by_id' => $this->manager->id],
            'teacher',
            'stages',
            [$this->programme->id],
        );

        expect(collect($made)->pluck('assigned_to_id'))->not->toContain($farTeacher->id);
    });
});

describe('a set raised whole', function () {
    it('writes a template\'s tasks with dates read from the day it is applied', function () {
        $template = TaskTemplate::create(['name' => 'افتتاح الفصل']);

        $template->items()->createMany([
            ['title' => 'جهّز القاعات', 'due_offset_days' => -3, 'sort_order' => 1],
            ['title' => 'استقبل الطلاب', 'due_offset_days' => 0, 'sort_order' => 2,
                'steps' => ['التسجيل', 'التوزيع على الدفعات']],
        ]);

        $made = $this->service->applyTemplate($template, Carbon::parse('2026-10-01'), $this->manager, 'manager');

        expect($made)->toHaveCount(2);

        $before = collect($made)->firstWhere('title', 'جهّز القاعات');
        $onTheDay = collect($made)->firstWhere('title', 'استقبل الطلاب');

        expect($before->due_date->toDateString())->toBe('2026-09-28')
            ->and($onTheDay->due_date->toDateString())->toBe('2026-10-01')
            // Its steps came with it.
            ->and($onTheDay->steps()->pluck('title')->all())->toBe(['التسجيل', 'التوزيع على الدفعات'])
            ->and(collect($made)->pluck('batch_key')->unique())->toHaveCount(1);
    });

    it('raises a template item for every holder of an office', function () {
        $template = TaskTemplate::create(['name' => 'بداية الأسبوع']);
        $template->items()->create(['title' => 'راجع خطتك', 'assign_to_role' => 'teacher']);

        $made = $this->service->applyTemplate(
            $template, Carbon::parse('2026-10-01'), $this->manager, 'manager', 'stages', [$this->programme->id],
        );

        expect($made)->toHaveCount(2);
    });
});

describe('the steps inside a task', function () {
    it('answers how far through it is, and says nothing when it has no steps', function () {
        $bare = Task::create(['title' => 'بلا خطوات', 'status' => 'pending', 'created_by_type' => 'manager', 'created_by_id' => $this->manager->id]);

        // Not nought per cent: it simply does not answer this question, and an
        // empty bar would say something untrue about it.
        expect($bare->stepProgress())->toBeNull();

        $withSteps = Task::create(['title' => 'بخطوات', 'status' => 'pending', 'created_by_type' => 'manager', 'created_by_id' => $this->manager->id]);
        $withSteps->steps()->createMany([
            ['title' => 'الأولى', 'is_done' => true],
            ['title' => 'الثانية'],
            ['title' => 'الثالثة'],
            ['title' => 'الرابعة'],
        ]);

        expect($withSteps->fresh()->stepProgress())->toBe(25);
    });

    it('stamps the moment a step is ticked, and clears it when untickedded', function () {
        $task = Task::create(['title' => 'مهمة', 'status' => 'pending', 'created_by_type' => 'manager', 'created_by_id' => $this->manager->id]);
        $step = $task->steps()->create(['title' => 'خطوة', 'is_done' => true]);

        expect($step->done_at)->not->toBeNull();

        $step->update(['is_done' => false]);

        expect($step->fresh()->done_at)->toBeNull();
    });
});

describe('a slip that reaches somebody who can act', function () {
    it('tells the teacher\'s own supervisor, and only once', function () {
        $task = Task::create([
            'title' => 'متأخرة',
            'status' => 'pending',
            'due_date' => '2026-09-01',
            'assigned_to_type' => 'teacher',
            'assigned_to_id' => $this->teacher->id,
            'created_by_type' => 'manager',
            'created_by_id' => $this->manager->id,
        ]);

        expect($this->service->escalateOverdue(Carbon::parse('2026-09-05')))->toBe(1);

        $told = $task->fresh()->activities()->where('action', TaskActivity::ESCALATED)->first();

        expect($told->to_value)->toBe('مشرف البرنامج')
            // A man told the same thing seven nights running stops reading the
            // seventh, and the seventh is the one that mattered.
            ->and($this->service->escalateOverdue(Carbon::parse('2026-09-06')))->toBe(0);
    });

    it('leaves alone what is finished, and what is not yet due', function () {
        Task::create([
            'title' => 'منجزة', 'status' => 'completed', 'due_date' => '2026-09-01',
            'assigned_to_type' => 'teacher', 'assigned_to_id' => $this->teacher->id,
            'created_by_type' => 'manager', 'created_by_id' => $this->manager->id,
        ]);

        Task::create([
            'title' => 'قادمة', 'status' => 'pending', 'due_date' => '2026-09-30',
            'assigned_to_type' => 'teacher', 'assigned_to_id' => $this->teacher->id,
            'created_by_type' => 'manager', 'created_by_id' => $this->manager->id,
        ]);

        expect($this->service->escalateOverdue(Carbon::parse('2026-09-05')))->toBe(0);
    });
});

describe('the record of every hand', function () {
    it('opens with the raising, and reads back in Arabic', function () {
        $task = $this->service->raise([
            'title' => 'مهمة',
            'status' => 'pending',
            'created_by_type' => 'manager',
            'created_by_id' => $this->manager->id,
        ]);

        $this->service->record($task, $this->manager->id, 'manager', TaskActivity::STAGE, 'لم تبدأ', 'جارية');

        $lines = $task->fresh()->activities->map(fn (TaskActivity $a) => $a->say());

        expect($lines)->toHaveCount(2)
            ->and($lines->first())->toContain('نقلها من لم تبدأ إلى جارية')
            ->and($lines->last())->toContain('أنشأها');
    });
});

/**
 * The night's run, which is what makes any of this happen without a person
 * remembering it.
 */
describe('the nightly run', function () {
    it('raises the day\'s patterns and reports the slips in one pass', function () {
        TaskSeries::create([
            'title' => 'اليومية',
            'assigned_to_type' => 'teacher',
            'assigned_to_id' => $this->teacher->id,
            'every' => TaskSeries::DAILY,
            'starts_on' => '2026-09-01',
            'created_by_type' => 'manager',
            'created_by_id' => $this->manager->id,
        ]);

        Task::create([
            'title' => 'متأخرة', 'status' => 'pending', 'due_date' => '2026-09-01',
            'assigned_to_type' => 'teacher', 'assigned_to_id' => $this->teacher->id,
            'created_by_type' => 'manager', 'created_by_id' => $this->manager->id,
        ]);

        $this->artisan('tasks:follow-up', ['--on' => '2026-09-05'])
            ->expectsOutputToContain('أُنشئت 1 مهمة، وأُبلغ عن 1 متأخّرة.')
            ->assertSuccessful();
    });

    it('steps over a pattern nobody authored rather than dying on it', function () {
        // A series with no author cannot raise anything, and one bad row must
        // not leave every other pattern in the academy unraised for the night.
        TaskSeries::create([
            'title' => 'بلا صاحب',
            'assigned_to_type' => 'teacher',
            'assigned_to_id' => $this->teacher->id,
            'every' => TaskSeries::DAILY,
            'starts_on' => '2026-09-01',
        ]);

        TaskSeries::create([
            'title' => 'سليمة',
            'assigned_to_type' => 'teacher',
            'assigned_to_id' => $this->teacher->id,
            'every' => TaskSeries::DAILY,
            'starts_on' => '2026-09-01',
            'created_by_type' => 'manager',
            'created_by_id' => $this->manager->id,
        ]);

        expect($this->service->raiseDue(Carbon::parse('2026-09-05')))->toBe(1)
            ->and(Task::where('title', 'سليمة')->exists())->toBeTrue();
    });
});
