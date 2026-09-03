<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Screen;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserScreenOverride;
use App\Services\Reports\ReportCatalogue;
use App\Services\Reports\ReportQuery;
use App\Support\Access;
use App\Support\Scope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A report is written once and read by every role at its own reach.
 *
 * What it measures is its own; who may read it is a permission like any page's;
 * how much of the academy enters it is `Scope`. Keeping the three apart is what
 * stopped five reports existing in two copies each.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-10 08:00:00');

    $this->programmeA = Stage::factory()->create(['name' => 'البرنامج الأول']);
    $this->programmeB = Stage::factory()->create(['name' => 'البرنامج الثاني']);
    $this->cohortA = Circle::factory()->create(['name' => 'دفعة أ', 'stage_id' => $this->programmeA->id]);
    $this->cohortB = Circle::factory()->create(['name' => 'دفعة ب', 'stage_id' => $this->programmeB->id]);

    $this->salem = Student::factory()->create(['name' => 'سالم', 'circle_id' => $this->cohortA->id]);
    $this->ziad = Student::factory()->create(['name' => 'زياد', 'circle_id' => $this->cohortB->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohortA->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programmeA->id);

    $this->manager = Manager::factory()->create();

    foreach ([$this->salem, $this->ziad] as $student) {
        foreach (['2026-09-01', '2026-09-02'] as $date) {
            Attendance::create([
                'student_id' => $student->id,
                'circle_id' => $student->circle_id,
                'teacher_id' => $this->teacher->id,
                'date' => $date,
                'status' => 'present',
            ]);
        }
    }
});

function reportRows(User $user, string $role, string $key, string $groupBy = 'student'): array
{
    $result = ReportCatalogue::find($key)->run(new ReportQuery(
        scope: Scope::for($user, $role),
        from: Carbon::parse('2026-09-01'),
        to: Carbon::parse('2026-09-30'),
        groupBy: $groupBy,
    ));

    return array_column($result->rows, 'name');
}

it('offers the whole catalogue', function () {
    expect(array_map(fn ($r) => $r->key(), ReportCatalogue::all()))->toBe([
        'attendance', 'memorization', 'self-program', 'mutun', 'exams',
        'gamification', 'retention', 'family-contact',
        'teacher-performance', 'supervision', 'forms', 'tasks',
    ]);
});

describe('reach', function () {
    it('shows a centre manager everyone', function () {
        expect(reportRows($this->manager, 'manager', 'attendance'))
            ->toEqualCanonicalizing(['سالم', 'زياد']);
    });

    it('shows a cohort supervisor only his programme', function () {
        expect(reportRows($this->supervisor, 'supervisor', 'attendance'))->toBe(['سالم']);
    });

    it('shows a cohort teacher only his cohort', function () {
        expect(reportRows($this->teacher, 'teacher', 'memorization'))->toBe(['سالم']);
    });

    it('shows a guardian only his own child', function () {
        $guardian = Guardian::factory()->create();
        $this->ziad->update(['guardian_id' => $guardian->id]);

        expect(reportRows($guardian, 'guardian', 'attendance'))->toBe(['زياد']);
    });
});

describe('gathering', function () {
    it('gives a row per student', function () {
        expect(reportRows($this->manager, 'manager', 'attendance', ReportQuery::BY_STUDENT))
            ->toEqualCanonicalizing(['سالم', 'زياد']);
    });

    it('gathers by cohort', function () {
        expect(reportRows($this->manager, 'manager', 'attendance', ReportQuery::BY_CIRCLE))
            ->toEqualCanonicalizing(['دفعة أ', 'دفعة ب']);
    });

    it('gathers by programme', function () {
        expect(reportRows($this->manager, 'manager', 'attendance', ReportQuery::BY_STAGE))
            ->toEqualCanonicalizing(['البرنامج الأول', 'البرنامج الثاني']);
    });

    it('gathers the whole centre into one row', function () {
        $result = ReportCatalogue::find('attendance')->run(new ReportQuery(
            scope: Scope::for($this->manager, 'manager'),
            from: Carbon::parse('2026-09-01'),
            to: Carbon::parse('2026-09-30'),
            groupBy: ReportQuery::BY_CENTRE,
        ));

        expect($result->rows)->toHaveCount(1)
            ->and($result->rows[0]['name'])->toBe('المركز')
            ->and($result->rows[0]['students'])->toBe(2)
            ->and($result->rows[0]['present'])->toBe(4);
    });

    it('gathers a supervisor\'s centre row from his reach alone', function () {
        $result = ReportCatalogue::find('attendance')->run(new ReportQuery(
            scope: Scope::for($this->supervisor, 'supervisor'),
            from: Carbon::parse('2026-09-01'),
            to: Carbon::parse('2026-09-30'),
            groupBy: ReportQuery::BY_CENTRE,
        ));

        expect($result->rows[0]['students'])->toBe(1)
            ->and($result->rows[0]['present'])->toBe(2);
    });
});

describe('narrowing to one subject', function () {
    it('reports on a single student', function () {
        $result = ReportCatalogue::find('attendance')->run(new ReportQuery(
            scope: Scope::for($this->manager, 'manager'),
            from: Carbon::parse('2026-09-01'),
            to: Carbon::parse('2026-09-30'),
            subjectType: 'student',
            subjectId: $this->salem->id,
        ));

        expect(array_column($result->rows, 'name'))->toBe(['سالم']);
    });

    it('will not reach past the reader\'s own reach', function () {
        // The supervisor names a cohort outside his programme; it yields nothing
        // rather than reaching it.
        $result = ReportCatalogue::find('attendance')->run(new ReportQuery(
            scope: Scope::for($this->supervisor, 'supervisor'),
            from: Carbon::parse('2026-09-01'),
            to: Carbon::parse('2026-09-30'),
            subjectType: 'circle',
            subjectId: $this->cohortB->id,
        ));

        expect($result->rows)->toBe([]);
    });
});

describe('permission', function () {
    it('offers only the reports a role is granted', function () {
        $before = array_map(fn ($r) => $r->key(), ReportCatalogue::for(Scope::for($this->teacher, 'teacher')));

        // A cohort teacher does not start with the two that are not his to read.
        expect($before)->toContain('memorization')
            ->and($before)->not->toContain('teacher-performance')
            ->and($before)->not->toContain('forms');

        Screen::where('route_name', 'teacher.reports.memorization')->first()->permissions()->delete();
        Access::forget();

        $after = array_map(fn ($r) => $r->key(), ReportCatalogue::for(Scope::for($this->teacher, 'teacher')));

        expect($after)->not->toContain('memorization')
            ->and($after)->toContain('attendance');
    });

    it('honours an exception written for one person', function () {
        UserScreenOverride::create([
            'user_id' => $this->teacher->id,
            'screen_id' => Screen::where('route_name', 'teacher.reports.attendance')->value('id'),
            'is_allowed' => false,
        ]);

        $keys = array_map(fn ($r) => $r->key(), ReportCatalogue::for(Scope::for($this->teacher, 'teacher')));

        expect($keys)->not->toContain('attendance');
    });
});

describe('the screen', function () {
    it('renders for every role that has it', function () {
        foreach ([[$this->manager, 'manager'], [$this->supervisor, 'supervisor'], [$this->teacher, 'teacher']] as [$user, $role]) {
            $this->actingAs($user, $role)->get(route($role.'.reports'))->assertOk()->assertSee('التقارير');
        }
    });

    it('opens straight onto the report a link names', function () {
        Livewire::actingAs($this->teacher, 'teacher')
            ->test('shared.report-runner', ['report' => 'self-program'])
            ->assertSet('reportKey', 'self-program');
    });

    it('hands the table over as a spreadsheet', function () {
        Livewire::actingAs($this->manager, 'manager')
            ->test('shared.report-runner')
            ->set('from', '2026-09-01')
            ->set('to', '2026-09-30')
            ->call('exportCsv')
            ->assertFileDownloaded();
    });

    it('refuses to hand over a report the reader may not see', function () {
        // The permission that draws the table governs the file too.
        UserScreenOverride::create([
            'user_id' => $this->teacher->id,
            'screen_id' => Screen::where('route_name', 'teacher.reports.attendance')->value('id'),
            'is_allowed' => false,
        ]);

        Livewire::actingAs($this->teacher, 'teacher')
            ->test('shared.report-runner')
            ->set('reportKey', 'attendance')
            ->call('exportCsv')
            ->assertStatus(403);
    });
});
