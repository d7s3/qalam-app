<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\TaskSeries;
use App\Models\TaskTemplate;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A recurring pattern or a template is the work of whoever wrote it. The
 * automation screen is open to several offices, and each handles its own;
 * the manager, who answers for the academy, may handle anyone's.
 */
function seriesBy(string $role, int $authorId, string $title): TaskSeries
{
    return TaskSeries::create([
        'title' => $title,
        'every' => TaskSeries::WEEKLY,
        'on_weekday' => 1,
        'starts_on' => '2026-10-01',
        'is_active' => true,
        'created_by_type' => $role,
        'created_by_id' => $authorId,
    ]);
}

beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $this->supervisor->stages()->attach($this->programme->id);
    $this->manager = Manager::factory()->create(['is_approved' => true]);

    $this->managersSeries = seriesBy('manager', $this->manager->id, 'نمط المدير');
    $this->supervisorsSeries = seriesBy('supervisor', $this->supervisor->id, 'نمط المشرف');
    $this->managersTemplate = TaskTemplate::create(['name' => 'قالب المدير', 'created_by_type' => 'manager', 'created_by_id' => $this->manager->id]);
});

it('shows a supervisor only his own patterns and templates', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.task-automation')
        ->assertSee('نمط المشرف')
        ->assertDontSee('نمط المدير')
        ->set('tab', 'templates')
        ->assertDontSee('قالب المدير');
});

it('does not let a supervisor pause or delete the manager\'s pattern', function (string $action) {
    expect(fn () => Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.task-automation')
        ->call($action, $this->managersSeries->id))
        ->toThrow(ModelNotFoundException::class);

    expect($this->managersSeries->fresh())->not->toBeNull()
        ->and($this->managersSeries->fresh()->is_active)->toBeTrue();
})->with(['toggleSeries', 'deleteSeries']);

it('does not let a supervisor add to or apply the manager\'s template', function () {
    expect(fn () => Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.task-automation')
        ->call('addItem', $this->managersTemplate->id, 'بند دخيل'))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.task-automation')
        ->set('applyingTemplate', $this->managersTemplate->id)
        ->call('applyTemplate'))
        ->toThrow(ModelNotFoundException::class);

    expect($this->managersTemplate->items()->count())->toBe(0);
});

it('lets the supervisor pause his own pattern', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.task-automation')
        ->call('toggleSeries', $this->supervisorsSeries->id);

    expect($this->supervisorsSeries->fresh()->is_active)->toBeFalse();
});

it('lets the manager pause anyone\'s pattern', function () {
    Livewire::actingAs($this->manager, 'manager')
        ->test('shared.task-automation')
        ->call('toggleSeries', $this->supervisorsSeries->id);

    expect($this->supervisorsSeries->fresh()->is_active)->toBeFalse();
});

it('refuses to be switched to another office from the browser', function () {
    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.task-automation')
        ->set('asRole', 'manager');
})->throws(CannotUpdateLockedPropertyException::class);

it('assigns a pattern only to someone the supervisor reaches', function () {
    $stranger = Teacher::factory()->create(['is_approved' => true]);
    $stranger->circles()->attach(Circle::factory()->create()->id);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.task-automation')
        ->set('title', 'نمط لغريب')
        ->set('role', 'teacher')
        ->set('assignMode', 'person')
        ->set('personId', $stranger->id)
        ->call('saveSeries')
        ->assertForbidden();

    expect(TaskSeries::where('title', 'نمط لغريب')->exists())->toBeFalse();
});
