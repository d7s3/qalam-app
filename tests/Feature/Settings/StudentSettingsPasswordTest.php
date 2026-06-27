<?php

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = Student::factory()->create();
    $this->actingAs($this->student, 'student');
});

it('lets a student change their password from the settings page', function () {
    Livewire::test('student.settings')
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-123', $this->student->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password for the student', function () {
    Livewire::test('student.settings')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('password', $this->student->fresh()->password))->toBeTrue();
});

it('requires the password confirmation to match', function () {
    Livewire::test('student.settings')
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'different-456')
        ->call('updatePassword')
        ->assertHasErrors('password');

    expect(Hash::check('password', $this->student->fresh()->password))->toBeTrue();
});

it('still updates profile information without touching the password', function () {
    Livewire::test('student.settings')
        ->set('name', 'اسم محدث')
        ->set('email', 'updated@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($this->student->fresh()->name)->toBe('اسم محدث');
    expect(Hash::check('password', $this->student->fresh()->password))->toBeTrue();
});
