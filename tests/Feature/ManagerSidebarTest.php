<?php

use App\Models\Manager;
use App\Models\Student;

/**
 * The manager's sidebar: the academy's people first, and nothing lost.
 *
 * It ran to thirty-eight entries, longer than the screen, with the users and
 * the registration requests he deals with every day halfway down. Those now
 * stand at the top; the rest fold into groups that open on their own when he
 * is on one of their pages.
 */
beforeEach(function () {
    $this->actingAs(Manager::factory()->create(), 'manager');
});

it('still links every page it linked to', function (string $route) {
    $this->get(route('manager.dashboard'))
        ->assertSuccessful()
        ->assertSee('href="'.route($route).'"', false);
})->with([
    'manager.academic-calendar', 'manager.ai-analysis', 'manager.ai-settings', 'manager.api-docs',
    'manager.attendance-reports', 'manager.circles', 'manager.dashboard', 'manager.event-visibility',
    'manager.exam-levels', 'manager.exceeded-limits', 'manager.forms', 'manager.messages', 'manager.motivations',
    'manager.my-day', 'manager.pending-approvals', 'manager.period-values', 'manager.portal',
    'manager.quran-editor', 'manager.quranic-achievement', 'manager.reports', 'manager.role-permissions',
    'manager.self-program-progress', 'manager.self-program-record', 'manager.self-program-tracks',
    'manager.self-program-weeks', 'manager.settings', 'manager.stage-access', 'manager.stages',
    'manager.staff-members', 'manager.student-exams', 'manager.student-log', 'manager.students',
    'manager.task-automation', 'manager.task-board', 'manager.tasks', 'manager.user-access',
    'manager.whatsapp-settings', 'manager.yearly-attendance',
]);

it('puts the people and the requests first, and folds the rest away', function () {
    $html = $this->get(route('manager.dashboard'))->getContent();

    $first = fn (string $route) => strpos($html, 'href="'.route($route).'"');

    expect($first('manager.students'))->toBeLessThan($first('manager.self-program-weeks'))
        ->and($first('manager.pending-approvals'))->toBeLessThan($first('manager.portal'))
        ->and($first('manager.reports'))->toBeLessThan($first('manager.settings'))
        ->and($html)->toContain('data-flux-sidebar-group');
});

it('still counts the registration requests waiting for him', function () {
    Student::factory()->count(2)->create(['is_approved' => false, 'is_rejected' => false]);

    $this->get(route('manager.dashboard'))->assertSeeInOrder(['طلبات التسجيل', '2']);
});

it('opens the folded group of the page he is on, and only that one', function (string $route, bool $open) {
    $html = $this->get(route($route))->getContent();

    preg_match('/(<ui-disclosure[^>]*>)(?:(?!<\/ui-disclosure>).)*?إعدادات الواتساب/su', $html, $group);

    expect((bool) preg_match('/\sopen[\s>]/', $group[1] ?? ''))->toBe($open);
})->with([
    'on one of its pages' => ['manager.whatsapp-settings', true],
    'on the home page' => ['manager.dashboard', false],
]);
