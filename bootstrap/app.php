<?php

use App\Http\Middleware\EnsurePageIsEnabled;
use App\Http\Middleware\EnsureStartingPasswordWasChanged;
use App\Http\Middleware\EnsureUserIsApproved;
use App\Http\Middleware\RecordStudentVisit;
use App\Http\Middleware\RequirePendingSurveys;
use App\Http\Middleware\RoleMiddleware;
use App\Models\StudentEvent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'approved' => EnsureUserIsApproved::class,
            'password.changed' => EnsureStartingPasswordWasChanged::class,
            'role' => RoleMiddleware::class,
            'page.enabled' => EnsurePageIsEnabled::class,
            'surveys.required' => RequirePendingSurveys::class,
            'student.visits' => RecordStudentVisit::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // What broke in a student's hands, so it can be put right before he
        // stops coming. Reported as usual as well.
        $exceptions->report(function (Throwable $e) {
            // Asking who is signed in can itself fail when the database is
            // what broke; that must not become a second error.
            try {
                $student = auth('student')->user();
            } catch (Throwable) {
                return;
            }

            if ($student) {
                StudentEvent::record(
                    $student->id,
                    StudentEvent::PROBLEM,
                    request()->route()?->getName(),
                    class_basename($e).': '.$e->getMessage(),
                );
            }
        });
    })->create();
