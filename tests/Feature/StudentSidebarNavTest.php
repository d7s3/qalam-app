<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-07-07 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    AcademicCalendarEvent::create([
        'event_name' => 'دوام كامل',
        'start_date' => now()->subDays(30)->format('Y-m-d'),
        'end_date' => now()->addDays(30)->format('Y-m-d'),
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5, 6, 7],
        'is_visible' => true,
    ]);

    $this->actingAs($this->student, 'student');
});

it('renders the leaderboard anchor link and all real sidebar pages', function () {
    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSee('المتصدرون')
        ->assertSee('الاختبارات')
        ->assertSee('التقويم')
        ->assertSee('الرسائل');
});

it('has no more coming-soon placeholders now that every page is real', function () {
    $response = $this->get(route('student.dashboard'));
    $response->assertSuccessful();

    $html = $response->getContent();

    expect(substr_count($html, 'wire:key="soon-'))->toBe(0);
});
