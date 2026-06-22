<?php

use App\Livewire\Supervisor\Students;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Livewire\Livewire;

beforeEach(function () {
    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->circle2 = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);
});

it('allows supervisor to bulk select and change circle of students', function () {
    $student1 = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'status' => 'active',
    ]);
    $student2 = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $student1->id, (string) $student2->id])
        ->set('bulkCircleId', $this->circle2->id)
        ->call('applyBulkCircle')
        ->assertHasNoErrors();

    expect($student1->refresh()->circle_id)->toBe($this->circle2->id);
    expect($student2->refresh()->circle_id)->toBe($this->circle2->id);
});

it('allows supervisor to bulk change joined_at date of students', function () {
    $student1 = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'joined_at' => '2026-01-01',
    ]);
    $student2 = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'joined_at' => '2026-01-01',
    ]);

    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $student1->id, (string) $student2->id])
        ->set('bulkJoinedAt', '2026-06-01')
        ->call('applyBulkJoinedAt')
        ->assertHasNoErrors();

    expect($student1->refresh()->joined_at->format('Y-m-d'))->toBe('2026-06-01');
    expect($student2->refresh()->joined_at->format('Y-m-d'))->toBe('2026-06-01');
});

it('allows supervisor to bulk change status of students', function () {
    $student1 = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'status' => 'active',
    ]);
    $student2 = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $student1->id, (string) $student2->id])
        ->set('bulkStatus', 'suspended')
        ->call('applyBulkStatus')
        ->assertHasNoErrors();

    expect($student1->refresh()->status)->toBe('suspended');
    expect($student2->refresh()->status)->toBe('suspended');
});

it('allows supervisor to bulk reset access tokens', function () {
    $student1 = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'access_token' => 'old-token-1',
    ]);
    $student2 = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'access_token' => 'old-token-2',
    ]);

    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $student1->id, (string) $student2->id])
        ->call('applyBulkResetMagicLinks')
        ->assertHasNoErrors();

    expect($student1->refresh()->access_token)->not->toBe('old-token-1');
    expect($student2->refresh()->access_token)->not->toBe('old-token-2');
    expect($student1->refresh()->access_token)->not->toBeEmpty();
    expect($student2->refresh()->access_token)->not->toBeEmpty();
});

it('allows supervisor to bulk delete students with strict confirmation', function () {
    $student1 = Student::factory()->create([
        'circle_id' => $this->circle->id,
    ]);
    $student2 = Student::factory()->create([
        'circle_id' => $this->circle->id,
    ]);

    $this->actingAs($this->supervisor, 'supervisor');

    // Test with wrong confirmation input first
    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $student1->id, (string) $student2->id])
        ->set('deleteConfirmationInput', 'wrong input')
        ->call('confirmBulkDelete')
        ->assertHasNoErrors();

    expect(Student::find($student1->id))->not->toBeNull();
    expect(Student::find($student2->id))->not->toBeNull();

    // Test with correct confirmation input
    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $student1->id, (string) $student2->id])
        ->set('deleteConfirmationInput', 'تأكيد الحذف')
        ->call('confirmBulkDelete')
        ->assertHasNoErrors();

    expect(Student::find($student1->id))->toBeNull();
    expect(Student::find($student2->id))->toBeNull();
});
