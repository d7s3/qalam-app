<?php

use App\Models\Circle;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\Teacher;
use App\Services\Reports\Report;
use App\Services\Reports\ReportCatalogue;
use App\Services\Reports\ReportQuery;
use App\Support\Scope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * No report may name somebody its reader cannot see.
 *
 * This is the quiet failure, and the dangerous one. A page that collapses is
 * found the first time anybody opens it; a report that quietly counts the
 * programme next door is read, believed, and acted on — a supervisor comparing
 * his cohorts against numbers that were never his.
 *
 * Every report is confined by `Scope` rather than by anything written inside
 * it, which is what lets one report serve six roles. That arrangement is only
 * as good as its weakest report, so every one of them is asked the same
 * question here: given two programmes and a reader who holds one, does any name
 * from the other appear anywhere in what comes back?
 *
 * Names are the assertion because they survive every grouping. A report gathered
 * by student names students, by cohort names cohorts, by programme names
 * programmes — and in all three the other side's names must be absent.
 */
beforeEach(function () {
    Scope::forget();

    $this->mine = Stage::factory()->create(['name' => 'برنامجي']);
    $this->theirs = Stage::factory()->create(['name' => 'برنامجهم']);

    $this->myCohort = Circle::factory()->create(['stage_id' => $this->mine->id, 'name' => 'دفعتي']);
    $this->theirCohort = Circle::factory()->create(['stage_id' => $this->theirs->id, 'name' => 'دفعتهم']);

    $this->myStudent = Student::factory()->create([
        'name' => 'طالبي', 'circle_id' => $this->myCohort->id, 'stage_id' => $this->mine->id, 'is_approved' => true,
    ]);

    $this->theirStudent = Student::factory()->create([
        'name' => 'طالبهم', 'circle_id' => $this->theirCohort->id, 'stage_id' => $this->theirs->id, 'is_approved' => true,
    ]);

    $this->myTeacher = Teacher::factory()->create(['name' => 'معلمي']);
    $this->myTeacher->circles()->attach($this->myCohort->id);

    $this->theirTeacher = Teacher::factory()->create(['name' => 'معلمهم']);
    $this->theirTeacher->circles()->attach($this->theirCohort->id);

    $this->supervisor = Supervisor::factory()->create(['name' => 'مشرفي']);
    $this->supervisor->stages()->attach($this->mine->id);

    $this->theirSupervisor = Supervisor::factory()->create(['name' => 'مشرفهم']);
    $this->theirSupervisor->stages()->attach($this->theirs->id);

    // A task and a form on each side, so the two reports whose subject is
    // neither a student nor a teacher have something to say. A report that
    // returns nothing leaks nothing, and would pass the confinement test while
    // proving nothing about itself.
    // Each side's task set by its own supervisor. Making one man the author of
    // both would hand him the far side's task by authorship and read as a leak
    // that is really the fixture's doing.
    foreach ([[$this->myTeacher, $this->supervisor], [$this->theirTeacher, $this->theirSupervisor]] as [$teacher, $author]) {
        Task::create([
            'title' => "مهمة {$teacher->name}",
            'due_date' => Carbon::now()->subDay(),
            'status' => 'pending',
            'assigned_to_id' => $teacher->id,
            'assigned_to_type' => 'teacher',
            'created_by_id' => $author->id,
            'created_by_type' => 'supervisor',
        ]);
    }

    // Forms are named neutrally: a form's title is the academy's, not a
    // student's, and naming one after a student would make the report's own
    // subject look like somebody else's data.
    foreach ([['الأول', $this->myStudent], ['الثاني', $this->theirStudent]] as [$label, $student]) {
        $form = Form::create([
            'title' => "نموذج {$label}",
            'slug' => 'f-'.Str::random(8),
            'fields' => [],
            'audience' => 'students',
            'status' => 'published',
        ]);

        FormResponse::create([
            'form_id' => $form->id,
            'student_id' => $student->id,
            'answers' => [],
        ]);
    }
});

/** Everything a report printed, flattened into one string. */
function everythingSaidBy(Report $report, ReportQuery $query): string
{
    $result = $report->run($query);

    return json_encode(
        ['rows' => $result->rows, 'totals' => $result->totals, 'title' => $result->title],
        JSON_UNESCAPED_UNICODE,
    );
}

it('never names the programme next door, however the report is gathered', function () {
    $scope = Scope::for($this->supervisor, 'supervisor');
    $leaks = [];

    foreach (ReportCatalogue::all() as $report) {
        foreach ([ReportQuery::BY_STUDENT, ReportQuery::BY_CIRCLE, ReportQuery::BY_STAGE, ReportQuery::BY_CENTRE] as $groupBy) {
            $said = everythingSaidBy($report, new ReportQuery(
                scope: $scope,
                from: Carbon::now()->subMonth(),
                to: Carbon::now(),
                groupBy: $groupBy,
            ));

            foreach (['طالبهم', 'دفعتهم', 'معلمهم', 'برنامجهم', 'مشرفهم'] as $theirs) {
                if (str_contains($said, $theirs)) {
                    $leaks[] = "{$report->key()} (بتجميع {$groupBy}) ← سرّب «{$theirs}»";
                }
            }
        }
    }

    expect($leaks)->toBe([], PHP_EOL.'  '.implode(PHP_EOL.'  ', $leaks).PHP_EOL);
});

/**
 * The confinement above is only worth what the reports actually say.
 *
 * A report returning nothing leaks nothing, so a silent report passes the leak
 * test while proving exactly nothing about itself. The same reports are read
 * here by somebody who reaches the whole academy: each must name something from
 * the far side, or it is named as untested rather than counted as passed.
 */
it('proves each report says enough for the confinement to mean something', function () {
    $scope = Scope::for(Manager::factory()->create(), 'manager');
    $vacuous = [];

    foreach (ReportCatalogue::all() as $report) {
        $names = false;

        foreach ([ReportQuery::BY_STUDENT, ReportQuery::BY_CENTRE] as $groupBy) {
            $said = everythingSaidBy($report, new ReportQuery(
                scope: $scope,
                from: Carbon::now()->subMonth(),
                to: Carbon::now(),
                groupBy: $groupBy,
            ));

            foreach (['طالبهم', 'دفعتهم', 'معلمهم', 'برنامجهم', 'مشرفهم'] as $theirs) {
                $names = $names || str_contains($said, $theirs);
            }
        }

        if (! $names) {
            $vacuous[] = $report->key();
        }
    }

    // The forms report names forms, never people, so it cannot leak a name and
    // the test above cannot speak for it. What it can leak is a count, and the
    // test below asks it that question instead.
    expect($vacuous)->toBe(['forms'], 'تقارير لا تذكر أحداً، فاختبار التسريب يمرّ عليها فارغاً: '.implode('، ', $vacuous));
});

/**
 * The forms report counts rather than names, so its leak would be arithmetic.
 *
 * Both sides answered a form of their own. A supervisor over one programme must
 * be told about his own response and not the other — and a report that simply
 * dropped the far side's row would pass by accident, so the counts are read
 * rather than the rows.
 */
it('counts only the answers of students in reach', function () {
    $mine = collect(ReportCatalogue::find('forms')->run(new ReportQuery(
        scope: Scope::for($this->supervisor, 'supervisor'),
        from: Carbon::now()->subMonth(),
        to: Carbon::now(),
    ))->rows)->keyBy('name');

    Scope::forget();

    $centre = collect(ReportCatalogue::find('forms')->run(new ReportQuery(
        scope: Scope::for(Manager::factory()->create(), 'manager'),
        from: Carbon::now()->subMonth(),
        to: Carbon::now(),
    ))->rows)->keyBy('name');

    expect((int) $mine['نموذج الأول']['responses'])->toBe(1)
        // His own programme's answer, and not the one next door.
        ->and((int) ($mine['نموذج الثاني']['responses'] ?? 0))->toBe(0)
        // While the centre, which reaches both, is told about both.
        ->and((int) $centre['نموذج الأول']['responses'])->toBe(1)
        ->and((int) $centre['نموذج الثاني']['responses'])->toBe(1);
});
