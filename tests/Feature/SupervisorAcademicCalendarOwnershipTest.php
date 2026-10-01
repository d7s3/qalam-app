<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Manager;
use App\Models\Supervisor;
use App\Models\Task;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * On the supervisor's calendar an event is changed only by whoever wrote it —
 * saving and the bulk actions already held to that; deleting one did not.
 */
function calendarEventBy($author, array $attributes = []): AcademicCalendarEvent
{
    return AcademicCalendarEvent::create(array_merge([
        'event_name' => 'حدث',
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-05',
        'color' => 'indigo',
        'created_by_id' => $author->id,
        'created_by_type' => get_class($author),
    ], $attributes));
}

beforeEach(function () {
    $this->supervisor = Supervisor::factory()->create();
    $this->manager = Manager::factory()->create();
});

it('deletes an event the supervisor wrote', function () {
    $event = calendarEventBy($this->supervisor);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.academic-calendar')
        ->call('deleteEvent', $event->id);

    expect(AcademicCalendarEvent::whereKey($event->id)->exists())->toBeFalse();
});

it('does not delete an event someone else wrote', function () {
    $event = calendarEventBy($this->manager);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.academic-calendar')
        ->call('deleteEvent', $event->id)
        ->assertForbidden();

    expect(AcademicCalendarEvent::whereKey($event->id)->exists())->toBeTrue();
});

it('does not delete the manager\'s attendance period', function () {
    $period = calendarEventBy($this->manager, ['is_attendance_period' => true, 'weekdays' => [1, 2, 3, 4, 5], 'day_count' => 1]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.academic-calendar')
        ->call('deletePeriod', $period->id)
        ->assertForbidden();

    expect(AcademicCalendarEvent::whereKey($period->id)->exists())->toBeTrue();
});

it('completes only a task the supervisor gave or was given', function () {
    $teacher = Teacher::factory()->create();

    $theirs = Task::create([
        'title' => 'مهمة غيره',
        'status' => 'pending',
        'created_by_id' => $this->manager->id,
        'created_by_type' => get_class($this->manager),
        'assigned_to_id' => $teacher->id,
        'assigned_to_type' => get_class($teacher),
    ]);

    $his = Task::create([
        'title' => 'مهمته',
        'status' => 'pending',
        'created_by_id' => $this->manager->id,
        'created_by_type' => get_class($this->manager),
        'assigned_to_id' => $this->supervisor->id,
        'assigned_to_type' => get_class($this->supervisor),
    ]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.academic-calendar')
        ->call('completeTask', $theirs->id)
        ->assertForbidden();

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.academic-calendar')
        ->call('completeTask', $his->id);

    expect($theirs->fresh()->status)->toBe('pending');
    expect($his->fresh()->status)->toBe('completed');
});
