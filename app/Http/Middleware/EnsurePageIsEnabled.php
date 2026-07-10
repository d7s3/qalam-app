<?php

namespace App\Http\Middleware;

use App\Support\RolePages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePageIsEnabled
{
    protected const GUARDS = ['teacher', 'supervisor', 'guardian', 'student'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $role = null;
        foreach (self::GUARDS as $guard) {
            if ($request->user($guard)) {
                $role = $guard;
                break;
            }
        }

        if (! $role) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (! RolePages::isEnabled($role, $routeName)) {
            abort(403, __('هذه الصفحة غير متاحة لك حالياً. تواصل مع إدارة المجمع إذا كنت تحتاجها.'));
        }

        return $next($request);
    }
}
