<?php

use App\Models\Screen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * `Access` reads a route nobody registered as unrestricted, which is the only
 * safe reading — a page is written before anyone decides who may open it, and
 * refusing the undescribed would break the application on every addition.
 *
 * The cost is that such a page cannot be closed, because the administrator has
 * no switch to find. This holds the line: a role's page is either registered or
 * deliberately named below.
 */
it('leaves no role page outside the permission layer', function () {
    $screens = Screen::pluck('route_name')->all();

    /**
     * Pages that must open for anyone who passes the guard at all: the way in,
     * the way to finish signing up, the help, and the controller that opens a
     * screen held from another office — which asks permission against that
     * screen's own name rather than its own.
     */
    $alwaysOpen = [
        'manager.guide', 'supervisor.guide', 'teacher.guide', 'student.guide', 'staff.guide', 'guardian.guide',
        'manager.held', 'supervisor.held', 'staff.held',
        'teacher.complete-profile', 'student.complete-profile',
        'student.settings', 'staff.dashboard', 'staff.messages',
        'teacher.magic-link', 'supervisor.magic-link', 'guardian.magic-link',
        'manager.backup.download', 'manager.backup.download.stored',
        'teacher.download-plan-pdf', 'teacher.print-plan', 'student.show-plan',
        'guardian.student.challenge.create',
    ];

    $roles = ['manager', 'supervisor', 'teacher', 'student', 'guardian', 'staff'];
    $uncovered = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if (! $name || ! in_array(explode('.', $name)[0], $roles, true)) {
            continue;
        }

        if (! in_array('GET', $route->methods(), true) || in_array($name, $alwaysOpen, true)) {
            continue;
        }

        $covered = collect($screens)->contains(
            fn ($screen) => $name === $screen || str_starts_with($name, $screen.'.')
        );

        if (! $covered) {
            $uncovered[] = $name;
        }
    }

    expect($uncovered)->toBe([]);
});
