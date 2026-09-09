<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nobody works under the code he was handed.
 *
 * An account opened by the office above starts on a code the whole academy
 * knows, which is exactly why it must not outlive the first sign-in. Whoever
 * arrives on it is taken to one screen and kept there — not warned, not
 * reminded later — until he has chosen a password of his own.
 *
 * The change screen itself is let through, or the redirect would send him to
 * the page that redirects him.
 */
class EnsureStartingPasswordWasChanged
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('manager')
             ?? $request->user('supervisor')
             ?? $request->user('teacher')
             ?? $request->user('student')
             ?? $request->user('guardian')
             ?? $request->user('staff');

        if (! $user?->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('password.starting') || $request->routeIs('logout')) {
            return $next($request);
        }

        return redirect()->route('password.starting');
    }
}
