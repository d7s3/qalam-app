<?php

use App\Models\AppNotification;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Reports\ReportCatalogue;
use App\Services\Reports\ReportQuery;
use App\Support\Scope;
use App\Support\TaskAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The academy's chain of responsibility: who may ask what of whom, a word
 * before a day arrives, and whether a supervisor was there at all.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-20 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);

    $this->manager = Manager::factory()->create(['name' => 'المدير']);
    $this->supervisor = Supervisor::factory()->create(['name' => 'المشرف خالد']);
    $this->supervisor->stages()->attach($this->stage->id);

    $this->teacher = Teacher::factory()->create(['name' => 'أستاذ سعيد']);
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['name' => 'سالم', 'circle_id' => $this->circle->id]);

    // A programme nobody here supervises.
    $this->farStage = Stage::factory()->create();
    $this->farCircle = Circle::factory()->create(['stage_id' => $this->farStage->id]);
    $this->farTeacher = Teacher::factory()->create(['name' => 'أستاذ غريب']);
    $this->farTeacher->circles()->attach($this->farCircle->id);
    $this->farStudent = Student::factory()->create(['name' => 'زياد', 'circle_id' => $this->farCircle->id]);
});

describe('the chain of who may ask whom', function () {
    it('lets the centre manager ask anyone below him', function () {
        foreach ([$this->supervisor, $this->teacher, $this->student] as $recipient) {
            expect(TaskAssignment::allows(Scope::for($this->manager, 'manager'), $recipient))->toBeTrue();
        }
    });

    it('lets a supervisor ask his teachers and their students', function () {
        $scope = Scope::for($this->supervisor, 'supervisor');

        expect(TaskAssignment::allows($scope, $this->teacher))->toBeTrue()
            ->and(TaskAssignment::allows($scope, $this->student))->toBeTrue();
    });

    it('lets a teacher ask his own students', function () {
        expect(TaskAssignment::allows(Scope::for($this->teacher, 'teacher'), $this->student))->toBeTrue();
    });

    it('never lets anyone ask upward', function () {
        expect(TaskAssignment::allows(Scope::for($this->teacher, 'teacher'), $this->supervisor))->toBeFalse()
            ->and(TaskAssignment::allows(Scope::for($this->supervisor, 'supervisor'), $this->manager))->toBeFalse();
    });

    it('never lets anyone ask a peer', function () {
        $other = Teacher::factory()->create();
        $other->circles()->attach($this->circle->id);

        expect(TaskAssignment::allows(Scope::for($this->teacher, 'teacher'), $other))->toBeFalse();
    });

    it('stops at the edge of a reach', function () {
        // A supervisor's office permits asking a teacher; it is being *that*
        // teacher's supervisor that makes it his to ask.
        $scope = Scope::for($this->supervisor, 'supervisor');

        expect(TaskAssignment::allows($scope, $this->farTeacher))->toBeFalse()
            ->and(TaskAssignment::allows($scope, $this->farStudent))->toBeFalse();
    });

    it('gives a student nobody to ask', function () {
        expect(TaskAssignment::mayAssign('student'))->toBeFalse()
            ->and(TaskAssignment::candidatesFor(Scope::for($this->student, 'student')))->toBeEmpty();
    });

    it('offers a supervisor exactly those within his reach', function () {
        $names = TaskAssignment::candidatesFor(Scope::for($this->supervisor, 'supervisor'))->pluck('name');

        expect($names)->toContain('أستاذ سعيد')
            ->and($names)->toContain('سالم')
            ->and($names)->not->toContain('أستاذ غريب')
            ->and($names)->not->toContain('زياد');
    });
});

describe('a word before the day', function () {
    function upcoming(User $for, string $due, ?int $daysBefore): Task
    {
        return Task::create([
            'title' => 'مهمة', 'due_date' => $due, 'status' => 'pending',
            'remind_days_before' => $daysBefore,
            'assigned_to_id' => $for->id, 'assigned_to_type' => $for::class,
            'created_by_id' => test()->manager->id, 'created_by_type' => Manager::class,
        ]);
    }

    it('warns when the day is within the window', function () {
        upcoming($this->teacher, '2026-09-22', 3);

        $this->artisan('tasks:remind')->assertSuccessful();

        expect(AppNotification::where('type', 'task_due_soon')->count())->toBe(1);
    });

    it('says nothing while the day is still far off', function () {
        upcoming($this->teacher, '2026-10-15', 3);

        $this->artisan('tasks:remind');

        expect(AppNotification::where('type', 'task_due_soon')->count())->toBe(0);
    });

    it('says nothing about a task already past its day', function () {
        // An overdue task is chased, not warned about.
        upcoming($this->teacher, '2026-09-10', 3);

        $this->artisan('tasks:remind');

        expect(AppNotification::where('type', 'task_due_soon')->count())->toBe(0);
    });

    it('warns once and not every night after', function () {
        upcoming($this->teacher, '2026-09-22', 3);

        $this->artisan('tasks:remind');
        $this->artisan('tasks:remind');
        $this->artisan('tasks:remind');

        expect(AppNotification::where('type', 'task_due_soon')->count())->toBe(1);
    });

    it('leaves a finished task alone', function () {
        upcoming($this->teacher, '2026-09-22', 3)->update(['status' => 'completed']);

        $this->artisan('tasks:remind');

        expect(AppNotification::where('type', 'task_due_soon')->count())->toBe(0);
    });

    it('asks for no warning when none was wanted', function () {
        upcoming($this->teacher, '2026-09-22', null);

        $this->artisan('tasks:remind');

        expect(AppNotification::where('type', 'task_due_soon')->count())->toBe(0);
    });
});

describe('whether a supervisor was there', function () {
    function supervisionRows(User $user, string $role): array
    {
        return ReportCatalogue::find('supervision')->run(new ReportQuery(
            scope: Scope::for($user, $role),
            from: Carbon::parse('2026-09-01'),
            to: Carbon::parse('2026-09-30'),
        ))->rows;
    }

    it('names the teachers a supervisor asked nothing of', function () {
        $second = Teacher::factory()->create(['name' => 'أستاذ ثانٍ']);
        $second->circles()->attach($this->circle->id);

        Task::create([
            'title' => 'مهمة', 'due_date' => '2026-09-25', 'status' => 'pending',
            'assigned_to_id' => $this->teacher->id, 'assigned_to_type' => Teacher::class,
            'created_by_id' => $this->supervisor->id, 'created_by_type' => Supervisor::class,
        ]);

        $row = collect(supervisionRows($this->manager, 'manager'))->firstWhere('name', 'المشرف خالد');

        expect($row['teachers'])->toBe(2)
            ->and($row['followed'])->toBe(1)
            // The plainest figure, and the one that matters most.
            ->and($row['untouched'])->toBe(1)
            ->and($row['coverage'])->toBe('50%');
    });

    it('counts what he asked and what came of it', function () {
        foreach ([['2026-09-25', 'completed'], ['2026-09-25', 'pending']] as [$due, $status]) {
            Task::create([
                'title' => 'مهمة', 'due_date' => $due, 'status' => $status,
                'assigned_to_id' => $this->teacher->id, 'assigned_to_type' => Teacher::class,
                'created_by_id' => $this->supervisor->id, 'created_by_type' => Supervisor::class,
            ]);
        }

        $row = collect(supervisionRows($this->manager, 'manager'))->firstWhere('name', 'المشرف خالد');

        expect($row['assigned'])->toBe(2)
            ->and($row['done'])->toBe(1)
            ->and($row['on_time'])->toBe(1)
            ->and($row['completion'])->toBe('50%');
    });

    it('shows a supervisor only the supervisors of his own reach', function () {
        $far = Supervisor::factory()->create(['name' => 'مشرف بعيد']);
        $far->stages()->attach($this->farStage->id);

        $names = array_column(supervisionRows($this->supervisor, 'supervisor'), 'name');

        expect($names)->toContain('المشرف خالد')
            ->and($names)->not->toContain('مشرف بعيد');
    });
});

describe('the screen that assigns them', function () {
    it('offers only those within the asker\u2019s chain and reach', function () {
        $offered = collect(
            Livewire\Livewire::actingAs($this->supervisor, 'supervisor')
                ->test('supervisor.tasks-manager')
                ->instance()
                ->assignableUsers()
        )->flatMap(fn (array $group) => $group['users']->pluck('name'));

        expect($offered)->toContain('أستاذ سعيد')
            ->and($offered)->toContain('سالم')
            ->and($offered)->not->toContain('أستاذ غريب')
            ->and($offered)->not->toContain('المدير');
    });

    it('refuses an assignment the chain does not allow, however it is asked', function () {
        // The id arrives from the browser, so it is answered for in the server
        // and not only in the list the screen drew.
        $task = Task::create([
            'title' => 'مهمة', 'status' => 'pending',
            'created_by_id' => $this->supervisor->id, 'created_by_type' => Supervisor::class,
        ]);

        Livewire\Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.tasks-manager')
            ->call('updateTaskAssignee', $task->id, Teacher::class, $this->farTeacher->id);

        expect($task->fresh()->assigned_to_id)->toBeNull();
    });

    it('announces a moved date afresh', function () {
        $task = Task::create([
            'title' => 'مهمة', 'due_date' => '2026-09-22', 'status' => 'pending',
            'remind_days_before' => 3, 'reminded_at' => now(),
            'assigned_to_id' => $this->teacher->id, 'assigned_to_type' => Teacher::class,
            'created_by_id' => $this->supervisor->id, 'created_by_type' => Supervisor::class,
        ]);

        Livewire\Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.tasks-manager')
            ->call('updateTaskDueDate', $task->id, '2026-09-28');

        // The warning already given was for a day that no longer applies.
        expect($task->fresh()->reminded_at)->toBeNull();
    });
});
