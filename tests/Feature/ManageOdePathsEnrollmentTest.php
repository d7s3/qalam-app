<?php

use App\Livewire\Supervisor\ManageOdePaths;
use App\Models\Circle;
use App\Models\Ode;
use App\Models\OdePath;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentOdePlan;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $stage = Stage::create(['name' => 'مرحلة المنظومات']);
    $this->circle = Circle::create(['name' => 'دفعة المنظومات', 'stage_id' => $stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($stage->id);

    $this->path = OdePath::create([
        'ode_id' => Ode::create(['name' => 'تحفة الأطفال'])->id,
        'name' => 'مسار التحفة',
        'start_date' => '2026-09-01',
    ]);

    $this->actingAs($this->supervisor, 'supervisor');
});

it('enrolls the supervisor\'s own students', function () {
    $student = Student::factory()->create(['circle_id' => $this->circle->id]);

    Livewire::test(ManageOdePaths::class)
        ->call('showEnrollModal', $this->path->id)
        ->set('selectedStudentIds', [(string) $student->id])
        ->call('enrollStudents');

    expect(StudentOdePlan::where('student_id', $student->id)->where('status', 'active')->exists())->toBeTrue();
});

it('neither enrolls nor suspends a student outside the supervisor\'s reach', function () {
    $farCircle = Circle::create(['name' => 'دفعة بعيدة', 'stage_id' => Stage::create(['name' => 'مرحلة أخرى'])->id]);
    $enrolledElsewhere = Student::factory()->create(['circle_id' => $farCircle->id]);
    $stranger = Student::factory()->create(['circle_id' => $farCircle->id]);

    $theirPlan = StudentOdePlan::create([
        'student_id' => $enrolledElsewhere->id,
        'ode_path_id' => $this->path->id,
        'start_date' => '2026-09-01',
        'status' => 'active',
        'created_by_role' => 'supervisor',
    ]);

    Livewire::test(ManageOdePaths::class)
        ->call('showEnrollModal', $this->path->id)
        ->set('selectedStudentIds', [(string) $stranger->id])
        ->call('enrollStudents');

    expect($theirPlan->fresh()->status)->toBe('active');
    expect(StudentOdePlan::where('student_id', $stranger->id)->exists())->toBeFalse();
});
