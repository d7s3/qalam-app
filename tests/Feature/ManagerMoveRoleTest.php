<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Moving a person between offices, as against granting him a second one. A
 * supervisor who becomes a teacher stops being a supervisor, and what he held
 * as one has to be let go of — otherwise Scope goes on answering for the office
 * he left.
 */
beforeEach(function () {
    $this->manager = Manager::factory()->create();
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->actingAs($this->manager, 'manager');
});

/** The component as the users page mounts it. */
function roleBox(User $person, string $guard)
{
    return Livewire::test('manager.add-linked-role', [
        'sourceGuard' => $guard,
        'sourceId' => $person->id,
        'sourceName' => $person->name,
    ]);
}

it('turns a supervisor into a teacher', function () {
    $person = Supervisor::factory()->create(['name' => 'أبو بكر']);
    $person->stages()->attach($this->programme->id);

    roleBox($person, 'supervisor')->call('move', 'teacher');

    expect(UserRole::where('user_id', $person->id)->pluck('role')->all())->toBe(['teacher']);

    // And the programmes he supervised are no longer his: leaving them attached
    // would keep him inside a reach his new office does not give him.
    expect($person->stages()->count())->toBe(0);
});

it('turns a teacher into a supervisor and releases his cohorts', function () {
    $person = Teacher::factory()->create();
    $person->circles()->attach($this->cohort->id);

    roleBox($person, 'teacher')->call('move', 'supervisor');

    expect(UserRole::where('user_id', $person->id)->pluck('role')->all())->toBe(['supervisor']);
    expect($person->circles()->count())->toBe(0);
});

it('keeps it one account rather than making a second', function () {
    $person = Teacher::factory()->create(['email' => 'one@example.test']);

    roleBox($person, 'teacher')->call('move', 'supervisor');

    expect(User::where('email', 'one@example.test')->count())->toBe(1);
    expect(Supervisor::where('email', 'one@example.test')->exists())->toBeTrue();
    expect(Teacher::where('email', 'one@example.test')->exists())->toBeFalse();
});

it('leaves what he recorded exactly where it is', function () {
    $person = Teacher::factory()->create();
    $person->circles()->attach($this->cohort->id);

    $student = Student::factory()->create(['circle_id' => $this->cohort->id]);

    $record = Attendance::create([
        'student_id' => $student->id,
        'teacher_id' => $person->id,
        'circle_id' => $this->cohort->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);

    roleBox($person, 'teacher')->call('move', 'supervisor');

    // Moving him does not unhappen what he did.
    expect($record->fresh())->not->toBeNull();
    expect($record->fresh()->teacher_id)->toBe($person->id);
});

it('refuses to move a student who has been taught', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohort->id]);

    Attendance::create([
        'student_id' => $student->id,
        'circle_id' => $this->cohort->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);

    roleBox($student, 'student')->call('move', 'teacher');

    // His record only means anything for a student; moving him would leave all
    // of it pointing at somebody who is no longer one.
    expect(UserRole::where('user_id', $student->id)->pluck('role')->all())->toBe(['student']);
});

it('moves a student who has no record yet', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohort->id]);

    roleBox($student, 'student')->call('move', 'teacher');

    expect(UserRole::where('user_id', $student->id)->pluck('role')->all())->toBe(['teacher']);
    expect($student->fresh()->circle_id)->toBeNull();
});

it('refuses to move a guardian who has children attached', function () {
    $guardian = Guardian::factory()->create();
    Student::factory()->create(['guardian_id' => $guardian->id]);

    roleBox($guardian, 'guardian')->call('move', 'teacher');

    expect(UserRole::where('user_id', $guardian->id)->pluck('role')->all())->toBe(['guardian']);
});

it('ignores a move to the office he is already in', function () {
    $person = Teacher::factory()->create();

    roleBox($person, 'teacher')->call('move', 'teacher');

    expect(UserRole::where('user_id', $person->id)->pluck('role')->all())->toBe(['teacher']);
});
