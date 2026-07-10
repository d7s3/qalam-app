<?php

use App\Livewire\Auth\Student\Register;
use App\Models\Manager;
use App\Models\Student;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('notifies every manager when someone self-registers a new account', function () {
    $managerA = Manager::factory()->create();
    $managerB = Manager::factory()->create();

    Livewire::test(Register::class)
        ->set('name', 'طالب جديد')
        ->set('email', 'new-student@example.com')
        ->set('phone', '0512345678')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register')
        ->assertHasNoErrors();

    $student = Student::where('email', 'new-student@example.com')->first();
    expect($student)->not->toBeNull();
    expect($student->is_approved)->toBeFalse();

    expect(NotificationService::unreadCountFor('manager', $managerA->id))->toBe(1);
    expect(NotificationService::unreadCountFor('manager', $managerB->id))->toBe(1);
});
