<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Teacher;

/**
 * The teacher's sidebar: the register first, and nothing lost.
 *
 * The page a teacher opens every day — the attendance register — sat at the
 * foot of some twenty-five entries, below pages he opens once a term. The
 * everyday ones now stand at the top, the rest fold into groups that open on
 * their own when he is on one of their pages.
 */
beforeEach(function () {
    $this->teacher = Teacher::factory()->create(['is_approved' => true]);
    $this->teacher->circles()->attach(Circle::factory()->create(['stage_id' => Stage::factory()->create()->id])->id);

    $this->actingAs($this->teacher, 'teacher');
});

it('still links every page it linked to', function (string $route) {
    $this->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertSee('href="'.route($route).'"', false);
})->with([
    'teacher.attendance', 'teacher.dashboard', 'teacher.discipline', 'teacher.exceeded-limits', 'teacher.forms',
    'teacher.leaderboards', 'teacher.messages', 'teacher.motivations', 'teacher.my-day', 'teacher.ode-plans',
    'teacher.pairs', 'teacher.plan-creator', 'teacher.portal', 'teacher.quranic-discipline', 'teacher.reports',
    'teacher.self-program', 'teacher.self-program-record', 'teacher.self-program-weeks', 'teacher.student-exams',
    'teacher.student-log', 'teacher.student-plans', 'teacher.students', 'teacher.task-automation',
    'teacher.task-board', 'teacher.tasmeeh',
]);

it('puts the register and the everyday pages first, and folds the rest away', function () {
    $html = $this->get(route('teacher.dashboard'))->getContent();

    $first = fn (string $route) => strpos($html, 'href="'.route($route).'"');

    expect($first('teacher.attendance'))->toBeLessThan($first('teacher.self-program-weeks'))
        ->and($first('teacher.tasmeeh'))->toBeLessThan($first('teacher.discipline'))
        ->and($first('teacher.students'))->toBeLessThan($first('teacher.portal'))
        ->and($html)->toContain('data-flux-sidebar-group');
});

it('opens the folded group of the page he is on, and only that one', function (string $route, bool $open) {
    $html = $this->get(route($route))->getContent();

    preg_match('/(<ui-disclosure[^>]*>)(?:(?!<\/ui-disclosure>).)*?الانضباط الحضوري/su', $html, $group);

    expect((bool) preg_match('/\sopen[\s>]/', $group[1] ?? ''))->toBe($open);
})->with([
    'on one of its pages' => ['teacher.discipline', true],
    'on the home page' => ['teacher.dashboard', false],
]);
