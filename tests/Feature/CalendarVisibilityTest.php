<?php

use App\Models\AcademicCalendarEvent;
use App\Models\CalendarEventGrant;
use App\Models\Manager;
use App\Models\Supervisor;
use App\Support\CalendarVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Sight travels down one office at a time and never widens. The manager lets
 * the supervisor see ten events; the supervisor may pass on ten of them, or
 * five, never an eleventh.
 */
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

it('leaves the office that made it seeing its own', function () {
    expect(CalendarVisibility::canSee($this->event, 'manager'))->toBeTrue();
    expect(CalendarVisibility::canSee($this->event, 'supervisor'))->toBeFalse();
});

it('hands sight down one step', function () {
    expect(CalendarVisibility::grant($this->event, 'manager', 'supervisor', $this->manager))->toBeTrue();

    expect(CalendarVisibility::canSee($this->event, 'supervisor'))->toBeTrue();
    expect(CalendarVisibility::canSee($this->event, 'teacher'))->toBeFalse();
});

it('lets the receiver pass on what he was given', function () {
    CalendarVisibility::grant($this->event, 'manager', 'supervisor', $this->manager);

    $supervisor = Supervisor::factory()->create();

    expect(CalendarVisibility::grant($this->event, 'supervisor', 'teacher', $supervisor))->toBeTrue();
    expect(CalendarVisibility::canSee($this->event, 'teacher'))->toBeTrue();
});

it('refuses an office handing on what it cannot see', function () {
    $supervisor = Supervisor::factory()->create();

    // Nobody gave him this event, so he has nothing to give.
    expect(CalendarVisibility::grant($this->event, 'supervisor', 'teacher', $supervisor))->toBeFalse();
    expect(CalendarVisibility::canSee($this->event, 'teacher'))->toBeFalse();
});

it('refuses to hand sight upward or sideways', function () {
    CalendarVisibility::grant($this->event, 'manager', 'supervisor', $this->manager);

    $supervisor = Supervisor::factory()->create();

    // The manager is above him and the guardian is beside him.
    expect(CalendarVisibility::grant($this->event, 'supervisor', 'manager', $supervisor))->toBeFalse();
    expect(CalendarVisibility::grant($this->event, 'supervisor', 'guardian', $supervisor))->toBeFalse();
});

it('takes the whole chain back when the top withdraws', function () {
    CalendarVisibility::grant($this->event, 'manager', 'supervisor', $this->manager);
    CalendarVisibility::grant($this->event, 'supervisor', 'teacher', Supervisor::factory()->create());

    expect(CalendarVisibility::canSee($this->event, 'teacher'))->toBeTrue();

    CalendarVisibility::revoke($this->event, 'manager', 'supervisor');

    // The teacher's own row is untouched and his sight is gone all the same:
    // a grant is only as good as its grantor's, and nothing had to be swept up.
    expect(CalendarVisibility::canSee($this->event, 'supervisor'))->toBeFalse();
    expect(CalendarVisibility::canSee($this->event, 'teacher'))->toBeFalse();
    expect(CalendarEventGrant::count())->toBe(1);
});

it('gives the whole roll of offices that can see it', function () {
    CalendarVisibility::grant($this->event, 'manager', 'supervisor', $this->manager);
    CalendarVisibility::grant($this->event, 'supervisor', 'teacher', Supervisor::factory()->create());

    expect(CalendarVisibility::rolesSeeing($this->event))
        ->toContain('manager')->toContain('supervisor')->toContain('teacher')
        ->not->toContain('student');
});

it('keeps the super administrator above all of it', function () {
    $admin = Manager::factory()->create(['is_super_admin' => true]);

    expect(CalendarVisibility::canSee($this->event, 'student', $admin))->toBeTrue();
});
