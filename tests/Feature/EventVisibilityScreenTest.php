<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Manager;
use App\Models\Supervisor;
use App\Support\CalendarVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = Manager::factory()->create();

    $this->event = AcademicCalendarEvent::create([
        'event_name' => 'لقاء المشرفين',
        'start_date' => '2026-09-06',
        'end_date' => '2026-09-06',
        'created_by_id' => $this->manager->id,
        'created_by_type' => Manager::class,
    ]);
});

it('opens for a manager and names who he may hand to', function () {
    $this->actingAs($this->manager, 'manager')
        ->get(route('manager.event-visibility'))
        ->assertSuccessful()
        ->assertSee('رؤية الأحداث')
        ->assertSee('لقاء المشرفين')
        // The offices he carries, drawn as buttons — the deepest markup.
        ->assertSee('مشرف دفعة')
        ->assertSee('معلم دفعة');
});

it('hands sight down and takes it back from the screen', function () {
    $component = Livewire::actingAs($this->manager, 'manager')
        ->test('shared.event-visibility')
        ->set('asRole', 'manager');

    $component->call('hand', $this->event->id, 'supervisor');
    expect(CalendarVisibility::canSee($this->event, 'supervisor'))->toBeTrue();

    $component->call('withdraw', $this->event->id, 'supervisor');
    expect(CalendarVisibility::canSee($this->event, 'supervisor'))->toBeFalse();
});

it('refuses a supervisor handing on what nobody gave him', function () {
    $supervisor = Supervisor::factory()->create();

    Livewire::actingAs($supervisor, 'supervisor')
        ->test('shared.event-visibility')
        ->set('asRole', 'supervisor')
        ->call('hand', $this->event->id, 'teacher')
        ->assertStatus(404);

    expect(CalendarVisibility::canSee($this->event, 'teacher'))->toBeFalse();
});
