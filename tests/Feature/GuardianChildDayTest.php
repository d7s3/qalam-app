<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\OccurrenceAttendance;
use App\Models\PeriodValue;
use App\Models\Stage;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The family had figures after the fact and no rhythm. This is the son's day as
 * the son sees it — and only that: answering for him stays his.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->guardian = Guardian::factory()->create();

    $this->child = Student::factory()->create([
        'name' => 'سالم',
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
        'guardian_id' => $this->guardian->id,
    ]);

    $this->lesson = AcademicCalendarEvent::create([
        'event_name' => 'درس التفسير',
        'formative_note' => 'يُقرأ فيه من تفسير السعدي.',
        'start_date' => now()->subMonth()->format('Y-m-d'),
        'end_date' => now()->addMonth()->format('Y-m-d'),
        'stage_ids' => [$this->programme->id],
        'is_attendance_period' => false,
    ]);
});

it('opens for a guardian and shows his son day', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.child-day'))
        ->assertSuccessful()
        ->assertSee('يوم ابني')
        ->assertSee('درس التفسير')
        // The formative note is what the family reads, not the admin description.
        ->assertSee('السعدي')
        // The deepest markup, which an inline @php would have cut away.
        ->assertSee('لم يُسجَّل');
});

it('shows what the circle is working on, for the home to reinforce', function () {
    PeriodValue::create([
        'title' => 'الصدق',
        'practice' => 'لا يمرّ يومٌ بكذبة، ولو مازحاً.',
        'stage_id' => $this->programme->id,
        'starts_on' => now()->subDay()->format('Y-m-d'),
        'ends_on' => now()->addDays(5)->format('Y-m-d'),
    ]);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.child-day'))
        ->assertSee('ما يعملون عليه')
        ->assertSee('الصدق')
        ->assertSee('ما يُعزَّز في البيت');
});

it('carries his answer through without letting the guardian give it', function () {
    OccurrenceAttendance::create([
        'academic_calendar_event_id' => $this->lesson->id,
        'date' => now()->format('Y-m-d'),
        'user_id' => $this->child->id,
        'role' => 'student',
        'status' => OccurrenceAttendance::PRESENT,
    ]);

    $component = Livewire::actingAs($this->guardian, 'guardian')->test('guardian.child-day');

    $component->assertSee('حاضر');

    // The window watches. Recording belongs to the person it is about, and
    // there is no method here that writes one.
    expect(method_exists($component->instance(), 'record'))->toBeFalse();
});

it('shows nobody else children', function () {
    $stranger = Student::factory()->create([
        'name' => 'ابن غيره',
        'circle_id' => $this->cohort->id,
        'guardian_id' => Guardian::factory()->create()->id,
    ]);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.child-day'))
        ->assertDontSee('ابن غيره');
});
