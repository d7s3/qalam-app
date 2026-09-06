<?php

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Every registered page reaches the role that owns it.
 *
 * The permission layer sits in front of seventy-odd screens across five roles,
 * and a mistake in it is invisible: a page simply stops appearing, with nothing
 * to say why. So the whole catalogue is walked rather than sampled — each screen
 * is asked whether its own role may open it, and the answer must be yes.
 */
beforeEach(function () {
    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);

    $this->people = [
        'manager' => Manager::factory()->create(),
        'supervisor' => tap(Supervisor::factory()->create(), fn ($s) => $s->stages()->attach($this->stage->id)),
        'teacher' => tap(Teacher::factory()->create(), fn ($t) => $t->circles()->attach($this->circle->id)),
        'student' => Student::factory()->create(['circle_id' => $this->circle->id]),
        'guardian' => Guardian::factory()->create(),
    ];
});

it('has a screen registered for every role', function () {
    $counts = Role::withCount('ownedScreens')->pluck('owned_screens_count', 'key');

    expect($counts['manager'])->toBeGreaterThan(20)
        ->and($counts['supervisor'])->toBeGreaterThan(15)
        ->and($counts['teacher'])->toBeGreaterThan(10)
        ->and($counts['student'])->toBeGreaterThan(8)
        // The guardian stays in the structure, and keeps his own pages.
        ->and($counts['guardian'])->toBeGreaterThan(3);
});

it('opens every screen a role is actually granted', function () {
    // Not every screen a role owns is granted to it — some are withheld until
    // an administrator hands them out, which is a state of its own and not a
    // fault. What must always hold is the other direction: a screen granted to
    // a role must open for it.
    $missing = [];

    foreach (Screen::with(['ownerRole', 'permissions'])->get() as $screen) {
        $role = $screen->ownerRole?->key;

        if (! $role || ! isset($this->people[$role])) {
            continue;
        }

        $granted = $screen->permissions->contains(fn ($permission) => $permission->role_id === $screen->owner_role_id);

        if ($granted && ! Access::canSee($this->people[$role], $role, $screen->route_name)) {
            $missing[] = "{$role} → {$screen->route_name} ({$screen->label})";
        }
    }

    expect($missing)->toBe([]);
});

it('withholds a screen from the role it was not granted to', function () {
    // The reports a guardian does not start with: he reads about his children,
    // and the academy's teachers and forms are not his to read.
    foreach (['guardian.reports.teacher-performance', 'guardian.reports.forms'] as $route) {
        expect(Access::canSee($this->people['guardian'], 'guardian', $route))->toBeFalse();
    }
});

it('keeps every screen pointing at a route that exists', function () {
    // A screen whose route was renamed becomes a dead sidebar entry: it renders,
    // and throws the moment anyone clicks it.
    $orphans = Screen::pluck('route_name')
        ->reject(fn (string $name) => Route::has($name))
        ->values()
        ->all();

    expect($orphans)->toBe([]);
});

it('opens every one of its own pages to each role over HTTP', function () {
    $failures = [];

    foreach ($this->people as $role => $person) {
        foreach (Screen::whereHas('ownerRole', fn ($q) => $q->where('key', $role))->get() as $screen) {
            if (! Route::has($screen->route_name)) {
                continue;
            }

            $route = Route::getRoutes()->getByName($screen->route_name);

            // Pages that need an id in the path are exercised by their own tests.
            if ($route && str_contains($route->uri(), '{')) {
                continue;
            }

            // Only what the role holds; a withheld screen answers 403 by design.
            $screen->loadMissing('permissions');

            if (! $screen->permissions->contains(fn ($p) => $p->role_id === $screen->owner_role_id)) {
                continue;
            }

            $status = $this->actingAs($person, $role)->get(route($screen->route_name))->getStatusCode();

            if ($status !== 200) {
                $failures[] = "{$role} → {$screen->route_name} = {$status}";
            }
        }
    }

    expect($failures)->toBe([]);
});

it('turns a role away from another role\'s pages', function () {
    // The guard is still the outer wall. A teacher who knows the address of a
    // supervisor page must not reach it, whatever his exceptions say.
    $teacher = $this->people['teacher'];

    foreach (['supervisor.circles', 'manager.settings', 'student.plan'] as $foreign) {
        $response = $this->actingAs($teacher, 'teacher')->get(route($foreign));

        expect($response->getStatusCode())->not->toBe(200);
    }
});
