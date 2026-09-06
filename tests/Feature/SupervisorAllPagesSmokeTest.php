<?php

use App\Models\Circle;
use App\Models\Form;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Smoke-tests every supervisor-facing page: seeds a realistic dataset, then
 * hits every named `supervisor.*` GET route and asserts it renders
 * successfully.
 */
it('loads every supervisor page without error', function () {
    $supervisor = Supervisor::factory()->create(['is_approved' => true]);

    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $supervisor->stages()->attach($stage->id);

    $circle = Circle::create(['name' => 'دفعة النور', 'stage_id' => $stage->id]);

    $teacher = Teacher::factory()->create(['is_approved' => true]);
    $teacher->circles()->attach($circle->id);

    Student::factory()->create(['circle_id' => $circle->id, 'is_approved' => true]);

    $competition = Leaderboard::create([
        'supervisor_id' => $supervisor->id,
        'circle_id' => $circle->id,
        'title' => 'مسابقة تجريبية',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
        'settings' => [],
    ]);
    $competition->circles()->attach($circle->id);

    $form = Form::create([
        'supervisor_id' => $supervisor->id,
        'title' => 'نموذج تجريبي',
        'slug' => 'test-form-'.uniqid(),
        'fields' => [],
    ]);

    Task::create([
        'title' => 'مهمة تجريبية',
        'created_by_type' => Supervisor::class,
        'created_by_id' => $supervisor->id,
        'assigned_to_type' => Supervisor::class,
        'assigned_to_id' => $supervisor->id,
    ]);

    $this->actingAs($supervisor, 'supervisor');

    $simpleRoutes = [
        'supervisor.dashboard',
        'supervisor.academic-calendar',
        'supervisor.circles',
        'supervisor.competitions',
        'supervisor.exceeded-limits',
        'supervisor.forms',
        'supervisor.forms.create',
        'supervisor.hadiths',
        'supervisor.hadiths.create-plan',
        'supervisor.hadiths.paths',
        'supervisor.messages',
        'supervisor.odes',
        'supervisor.odes.create-plan',
        'supervisor.odes.paths',
        'supervisor.odes.plans',
        'supervisor.students',
        'supervisor.tasks',
        'supervisor.teachers',
        'supervisor.whatsapp-settings',
        'supervisor.yearly-attendance',
    ];

    foreach ($simpleRoutes as $routeName) {
        $this->get(route($routeName))->assertSuccessful();
    }

    $this->get(route('supervisor.competitions.gamification', $competition->id))->assertSuccessful();
    $this->get(route('supervisor.competitions.standings', $competition->id))->assertSuccessful();
    $this->get(route('supervisor.forms.edit', $form->id))->assertSuccessful();
    $this->get(route('supervisor.forms.responses', $form->id))->assertSuccessful();
});
