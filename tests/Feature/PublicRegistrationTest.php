<?php

use App\Livewire\Auth\Student\Register;
use App\Models\Student;
use Livewire\Livewire;

it('renders the public registration page', function () {
    $this->get(route('register'))
        ->assertSuccessful()
        ->assertSee('إنشاء حساب جديد');
});

it('creates a pending student account on registration', function () {
    Livewire::test(Register::class)
        ->set('name', 'طالب جديد')
        ->set('email', 'newstudent@example.com')
        ->set('phone', '0512345678')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->set('terms', true)
        ->call('register')
        ->assertRedirect(route('student.dashboard'));

    $student = Student::where('email', 'newstudent@example.com')->first();

    expect($student)->not->toBeNull();
    expect($student->is_approved)->toBeFalse();
    expect($student->is_rejected)->toBeFalse();
});

it('requires accepting the terms checkbox', function () {
    Livewire::test(Register::class)
        ->set('name', 'طالب جديد')
        ->set('email', 'noterms@example.com')
        ->set('phone', '0512345678')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->set('terms', false)
        ->call('register')
        ->assertHasErrors('terms');
});

it('shows the create-account link on every role login page', function () {
    $this->get(route('student.login'))->assertSee(route('register'), false);
    $this->get(route('teacher.login'))->assertSee(route('register'), false);
    $this->get(route('supervisor.login'))->assertSee(route('register'), false);
    $this->get(route('parent.login'))->assertSee(route('register'), false);
});
