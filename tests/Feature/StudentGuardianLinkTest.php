<?php

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A student names his guardian and the guardian's account is made for him.
 * It must not be one anybody can open: the address and the password used to
 * be the guardian's own phone number, which is no secret at all.
 */
beforeEach(function () {
    $this->student = Student::factory()->create([
        'circle_id' => Circle::factory()->create()->id,
        'status' => 'active',
        'is_approved' => true,
        'guardian_id' => null,
    ]);

    $this->actingAs($this->student, 'student');
});

it('makes the guardian an account no one can guess into', function () {
    Livewire::test('student.guardian-notice')
        ->set('guardian_name', 'محمد أحمد')
        ->set('guardian_phone', '0501234567')
        ->call('save')
        ->assertHasNoErrors();

    $guardian = Guardian::findOrFail($this->student->fresh()->guardian_id);

    expect($guardian->phone)->toBe('966501234567')
        ->and(Hash::check('966501234567', $guardian->password))->toBeFalse()
        ->and(Hash::check('0501234567', $guardian->password))->toBeFalse();
});

it('does the same from the complete-profile screen', function () {
    $this->student->update(['is_data_completed' => false]);

    Livewire::test('student.complete-profile')
        ->set('email', 'me@example.com')
        ->set('password', 'secret-pass-123')
        ->set('password_confirmation', 'secret-pass-123')
        ->set('guardian_name', 'محمد أحمد')
        ->set('guardian_phone', '0501234567')
        ->call('save')
        ->assertHasNoErrors();

    $guardian = Guardian::findOrFail($this->student->fresh()->guardian_id);

    expect(Hash::check('966501234567', $guardian->password))->toBeFalse();
});

it('joins a brother to the guardian his family already has', function () {
    $guardian = Guardian::factory()->create(['phone' => '966501234567', 'is_approved' => true]);

    Livewire::test('student.guardian-notice')
        ->set('guardian_name', 'محمد أحمد')
        ->set('guardian_phone', '0501234567')
        ->call('save');

    expect($this->student->fresh()->guardian_id)->toBe($guardian->id)
        ->and(Guardian::count())->toBe(1);
});
