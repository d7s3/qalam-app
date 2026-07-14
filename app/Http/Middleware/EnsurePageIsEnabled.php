<?php

namespace App\Http\Middleware;

use App\Support\RolePages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsurePageIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return $next($request);
        }

        // The role is derived from the route's own name prefix (e.g.
        // "teacher.attendance" -> "teacher"), not from scanning guards for
        // any truthy session. A user can be authenticated under multiple
        // guards at once (e.g. a teacher who used the "login as student"
        // magic link), so checking guards in a fixed priority order would
        // silently apply the wrong role's permissions to the current page.
        $role = Str::before($routeName, '.');

        if (! $request->user($role)) {
            return $next($request);
        }

        if (! RolePages::isEnabled($role, $routeName)) {
            abort(403, __('هذه الصفحة غير متاحة لك حالياً. تواصل مع إدارة المجمع إذا كنت تحتاجها.'));
        }

        return $next($request);
    }
}
