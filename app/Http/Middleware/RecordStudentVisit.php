<?php

namespace App\Http\Middleware;

use App\Models\StudentEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notes each page a student opens, and each one that failed him.
 *
 * Page loads only — not the clicks inside a page — so the record reads as a
 * path through the platform rather than a flood.
 */
class RecordStudentVisit
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $student = $request->user('student');

        if ($student && $request->isMethod('GET') && ! $request->ajax()) {
            $status = $response->getStatusCode();

            StudentEvent::record(
                $student->id,
                $status >= 400 ? StudentEvent::PROBLEM : StudentEvent::PAGE,
                $request->route()?->getName(),
                $status >= 400 ? "HTTP {$status}" : null,
                $status,
            );
        }

        return $response;
    }
}
