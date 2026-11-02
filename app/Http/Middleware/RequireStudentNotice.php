<?php

namespace App\Http\Middleware;

use App\Models\StudentNotice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a student to the message written for him by name before any of his
 * pages, until he has opened it — with its code, when it was given one.
 *
 * It sits on the student's own pages only, so logging out and the message's
 * page itself stay open: the gate is always the way through itself.
 */
class RequireStudentNotice
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->user('student');

        if ($student && StudentNotice::waiting()->where('student_id', $student->id)->exists()) {
            return redirect()->route('student.notice');
        }

        return $next($request);
    }
}
