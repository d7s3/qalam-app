<?php

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Staff;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Every page of every office, opened once.
 *
 * Two hundred and eight routes carry the roles' screens and eighty-four of them
 * were named in a test — so a hundred and twenty-odd pages could break in a
 * refactor and nothing would say so until somebody opened one.
 *
 * The question asked here is the plainest one there is: does the person the
 * page was written for get the page? A five hundred is always a fault. A four
 * oh three to the office that owns the screen is a fault of a quieter kind —
 * the page exists, the man is who it is for, and something in the middle says
 * no.
 *
 * Pages taking a parameter are not opened: they need a row that means something
 * to the reader, which is a journey's business rather than a sweep's. They are
 * counted and named at the end so the gap is known rather than forgotten.
 */

/** Whoever holds an office fully, so nothing narrows him but the code under test. */
function actorFor(string $role): object
{
    $programme = Stage::first() ?? Stage::factory()->create(['name' => 'برنامج الاختبار']);
    $cohort = Circle::first() ?? Circle::factory()->create(['stage_id' => $programme->id]);

    return match ($role) {
        'manager' => Manager::factory()->create(['is_super_admin' => true]),
        'supervisor' => tap(Supervisor::factory()->create(), fn ($s) => $s->stages()->attach($programme->id)),
        'teacher' => tap(Teacher::factory()->create(), fn ($t) => $t->circles()->attach($cohort->id)),
        'student' => Student::factory()->create(['circle_id' => $cohort->id, 'stage_id' => $programme->id]),
        'guardian' => tap(Guardian::factory()->create(), function ($g) use ($cohort, $programme) {
            Student::factory()->create([
                'guardian_id' => $g->id,
                'circle_id' => $cohort->id,
                'stage_id' => $programme->id,
            ]);
        }),
        'staff' => Staff::factory()->create(),
    };
}

/** Every page of an office that opens without being handed a row. */
function pagesOf(string $role): array
{
    $pages = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if (! $name || ! in_array('GET', $route->methods(), true)) {
            continue;
        }

        if (strtok($name, '.') !== $role || str_contains($route->uri(), '{')) {
            continue;
        }

        $pages[$name] = $name;
    }

    ksort($pages);

    return $pages;
}

/**
 * Screens an office is deliberately not granted.
 *
 * The report routes are generated for every role from one list, so the route
 * exists under every prefix and the grants decide who actually opens it. These
 * are the ones nobody granted, and each for a reason: a supervision report and
 * a teacher-performance report are about a teacher rather than for him, and a
 * guardian is shown his own children rather than the academy's working.
 *
 * Pinned rather than merely allowed. A page that quietly stops opening for the
 * office that owns it is the failure this sweep exists to catch, and it would
 * hide in a rule that forgave every refusal.
 */
const REFUSED_ON_PURPOSE = [
    'teacher.reports.forms',
    'teacher.reports.supervision',
    'teacher.reports.teacher-performance',
    'guardian.reports.forms',
    'guardian.reports.retention',
    'guardian.reports.supervision',
    'guardian.reports.tasks',
    'guardian.reports.teacher-performance',
];

it('opens every page of every office', function () {
    $collapsed = [];
    $refused = [];
    $counted = 0;

    foreach (['manager', 'supervisor', 'teacher', 'student', 'guardian', 'staff'] as $role) {
        $actor = actorFor($role);

        foreach (pagesOf($role) as $page) {
            $counted++;

            try {
                $status = $this->actingAs($actor, $role)->get(route($page))->status();
            } catch (Throwable $e) {
                $collapsed[] = "{$page} ← ".class_basename($e).': '.str($e->getMessage())->limit(90);

                continue;
            }

            // A redirect is an answer, not a failure: a profile to complete, a
            // survey to answer, a starting code to change.
            if ($status === 500) {
                $collapsed[] = "{$page} ← انهارت (500)";
            } elseif ($status === 403) {
                $refused[] = $page;
            }
        }
    }

    expect($counted)->toBeGreaterThan(150, 'لم تُجمع المسارات — تحقّق من التصفية');

    expect($collapsed)->toBe([], PHP_EOL.'  '.implode(PHP_EOL.'  ', $collapsed).PHP_EOL);

    // Newly refused pages are the regression; newly opened ones mean somebody
    // granted a screen and this list has to say so on purpose.
    expect(array_values(array_diff($refused, REFUSED_ON_PURPOSE)))
        ->toBe([], 'صفحاتٌ صارت ممنوعة عن أصحابها: '.implode('، ', array_diff($refused, REFUSED_ON_PURPOSE)))
        ->and(array_values(array_diff(REFUSED_ON_PURPOSE, $refused)))
        ->toBe([], 'صفحاتٌ فُتحت ولم تعد ممنوعة — احذفها من القائمة: '.implode('، ', array_diff(REFUSED_ON_PURPOSE, $refused)));
});

/**
 * What this sweep does not reach, said out loud.
 *
 * A page taking a parameter needs a row that means something to the reader —
 * a plan of his, a student of his — which is a journey's business rather than a
 * sweep's. Counting them keeps the gap known instead of forgotten.
 */
it('knows how much of the application it does not open', function () {
    $roles = ['manager', 'supervisor', 'teacher', 'student', 'guardian', 'staff'];
    $withRows = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name && in_array('GET', $route->methods(), true)
            && in_array(strtok($name, '.'), $roles, true)
            && str_contains($route->uri(), '{')) {
            $withRows[] = $name;
        }
    }

    // Thirty-two pages wait on a journey to open them. When that number falls,
    // a journey covered one; when it rises, a page arrived uncovered.
    expect($withRows)->toHaveCount(32);
});
