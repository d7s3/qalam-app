<?php

namespace App\Http\Controllers;

use App\Models\Screen;
use App\Support\Access;
use App\Support\Scope;
use Illuminate\View\View;

/**
 * Opening a screen a reader holds through his office rather than owns.
 *
 * Seniority includes: what a cohort teacher may open, his supervisor may open.
 * But the teacher's own page sits behind the teacher's guard, and a supervisor
 * is not a teacher — so he reaches it here instead, under his own prefix and
 * his own guard, and the page renders exactly as it does for its owner.
 *
 * Permission is asked against the screen's own name, so this opens nothing that
 * the permission layer has not already granted: the same grants, the same
 * exceptions written for one person, the same chain of offices.
 */
class HeldScreenController extends Controller
{
    public function __invoke(string $screen): View
    {
        $registered = Screen::where('route_name', $screen)->firstOrFail();
        $scope = Scope::forRoute();

        // Asked in the reader's own role, about the screen's real name — which
        // is what the chain of offices and the personal exceptions are keyed to.
        abort_unless(
            Access::canSee($scope->user(), $scope->role(), $registered->route_name),
            403,
            __('هذه الصفحة غير متاحة لك حالياً.'),
        );

        // A screen says how it renders when its name does not: six of the
        // teacher's are tabs of one shell, and a page of their name still exists
        // from before the shell, showing something else entirely.
        $view = $registered->view ?: $registered->route_name;

        abort_unless(view()->exists($view), 404);

        return view($view, $registered->view_data ?? []);
    }
}
