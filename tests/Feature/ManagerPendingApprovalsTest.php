<?php

use App\Livewire\Manager\PendingApprovals;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = Manager::factory()->create();
    $this->actingAs($this->manager, 'manager');
});

it('lists pending requests from all four roles in one table', function () {
    $student = Student::factory()->create(['is_approved' => false]);
    $teacher = Teacher::factory()->create(['is_approved' => false]);
    $supervisor = Supervisor::factory()->create(['is_approved' => false]);
    $guardian = Guardian::factory()->create(['is_approved' => false]);

    Livewire::test(PendingApprovals::class)
        ->assertSee($student->name)
        ->assertSee($teacher->name)
        ->assertSee($supervisor->name)
        ->assertSee($guardian->name);
});

it('computes the four stat counts across all roles', function () {
    Student::factory()->create(['is_approved' => false]);
    Teacher::factory()->create(['is_approved' => true]);
    Supervisor::factory()->create(['is_approved' => false, 'is_rejected' => true]);

    $counts = Livewire::test(PendingApprovals::class)->instance()->counts();

    expect($counts['pending'])->toBe(1);
    expect($counts['approved'])->toBe(1);
    expect($counts['rejected'])->toBe(1);
    expect($counts['total'])->toBe(3);
});

it('approves a request as the same type by default', function () {
    $student = Student::factory()->create(['is_approved' => false]);

    Livewire::test(PendingApprovals::class)
        ->call('approve', $student->id, 'student');

    $student->refresh();
    expect($student->is_approved)->toBeTrue();
    expect($student->approved_by)->toBe($this->manager->id);
});

it('reassigns a self-registered student to a different role table on approval', function () {
    $student = Student::factory()->create(['is_approved' => false, 'email' => 'convert@example.com']);

    Livewire::test(PendingApprovals::class)
        ->set("reassignType.student-{$student->id}", 'teacher')
        ->call('approve', $student->id, 'student');

    expect(Student::find($student->id))->toBeNull();

    $teacher = Teacher::where('email', 'convert@example.com')->first();
    expect($teacher)->not->toBeNull();
    expect($teacher->is_approved)->toBeTrue();
    expect($teacher->name)->toBe($student->name);
});

it('refuses to reassign the type of a non-student request', function () {
    $teacher = Teacher::factory()->create(['is_approved' => false, 'email' => 'noreassign@example.com']);

    Livewire::test(PendingApprovals::class)
        ->set("reassignType.teacher-{$teacher->id}", 'supervisor')
        ->call('approve', $teacher->id, 'teacher');

    $teacher->refresh();
    expect($teacher->is_approved)->toBeFalse();
    expect(Supervisor::where('email', 'noreassign@example.com')->exists())->toBeFalse();
});

it('rejects a request by marking it rejected instead of deleting it', function () {
    $student = Student::factory()->create(['is_approved' => false]);

    Livewire::test(PendingApprovals::class)
        ->call('reject', $student->id, 'student');

    $student->refresh();
    expect($student->is_rejected)->toBeTrue();
    expect($student->is_approved)->toBeFalse();
    expect(Student::find($student->id))->not->toBeNull();
});

it('filters requests by search term', function () {
    Student::factory()->create(['name' => 'محمد أحمد الفريد', 'is_approved' => false]);
    Student::factory()->create(['name' => 'شخص آخر', 'is_approved' => false]);

    Livewire::test(PendingApprovals::class)
        ->set('search', 'الفريد')
        ->assertSee('محمد أحمد الفريد')
        ->assertDontSee('شخص آخر');
});

it('filters requests by role', function () {
    $student = Student::factory()->create(['is_approved' => false]);
    $teacher = Teacher::factory()->create(['is_approved' => false]);
    $supervisor = Supervisor::factory()->create(['is_approved' => false]);
    $guardian = Guardian::factory()->create(['is_approved' => false]);

    Livewire::test(PendingApprovals::class)
        ->set('roleFilter', 'teacher')
        ->assertSee($teacher->name)
        ->assertDontSee($student->name)
        ->assertDontSee($supervisor->name)
        ->assertDontSee($guardian->name);
});

it('combines the role filter with the status and search filters', function () {
    Student::factory()->create(['name' => 'طالب معلّق', 'is_approved' => false]);
    Teacher::factory()->create(['name' => 'معلم معلّق', 'is_approved' => false]);
    Teacher::factory()->create(['name' => 'معلم مقبول', 'is_approved' => true]);

    Livewire::test(PendingApprovals::class)
        ->set('roleFilter', 'teacher')
        ->set('statusFilter', 'pending')
        ->assertSee('معلم معلّق')
        ->assertDontSee('معلم مقبول')
        ->assertDontSee('طالب معلّق');
});
