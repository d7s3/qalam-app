<?php

use App\Livewire\Supervisor\Students as SupervisorStudents;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Regression guard for a class of bug where a valueless Alpine directive
 * (e.g. `@click.stop` / `x-on:click.stop`) placed on a Flux/Blade *component*
 * is rendered by Laravel's attribute bag as `@click.stop="@click.stop"`.
 *
 * Alpine then evaluates `@click.stop` as a JS expression and throws
 * `SyntaxError: Invalid or unexpected token`, which aborts Alpine
 * initialisation for the whole page — breaking every wire:/x-on: handler
 * on it (including the teacher sidebar navigation).
 *
 * A correctly written directive renders as `@click.stop=""` (empty value),
 * never `="@..."`, `="x-on:..."` or `="x-bind:..."`.
 */
function assertNoBrokenAlpineExpressions(string $html): void
{
    foreach (['="@', '="x-on:', '="x-bind:'] as $brokenSignature) {
        expect(str_contains($html, $brokenSignature))
            ->toBeFalse("Rendered HTML contains a broken Alpine attribute (value equal to its directive name): {$brokenSignature}");
    }
}

it('renders the supervisor students table without broken Alpine directives', function () {
    $stage = Stage::factory()->create();
    $circle = Circle::factory()->create(['stage_id' => $stage->id]);
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($stage->id);

    Student::factory()->create([
        'circle_id' => $circle->id,
        'status' => 'active',
        'access_token' => 'tok-123',
    ]);

    $this->actingAs($supervisor, 'supervisor');

    $html = Livewire::test(SupervisorStudents::class)->html();

    assertNoBrokenAlpineExpressions($html);
});

it('renders the teacher student-manager table without broken Alpine directives', function () {
    $stage = Stage::factory()->create();
    $circle = Circle::factory()->create(['stage_id' => $stage->id]);

    $teacher = Teacher::factory()->create([
        'is_approved' => true,
    ]);
    $teacher->circles()->attach($circle->id);

    Student::factory()->create([
        'circle_id' => $circle->id,
        'status' => 'active',
        'phone' => '0500000000',
        'access_token' => 'tok-abc',
    ]);

    $this->actingAs($teacher, 'teacher');

    $html = Livewire::test('teacher.student-manager')->html();

    assertNoBrokenAlpineExpressions($html);
});
