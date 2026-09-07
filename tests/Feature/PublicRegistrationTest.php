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

it('shows the create-account link on the unified login page', function () {
    $this->get(route('login'))->assertSee(route('register'), false);
});

/**
 * A password had to be twelve characters with an upper case, a lower case, a
 * digit and a symbol — a console operator's rule, applied to children signing
 * themselves up for a Quran circle, and told to nobody until it refused them.
 *
 * Six characters, whatever they are. What is kept is the check against
 * passwords already known to have been breached, and that runs only where the
 * accounts are real.
 */
it('lets a person choose a password of six characters', function () {
    Livewire::test(Register::class)
        ->set('name', 'طالب جديد')
        ->set('email', 'short@example.com')
        ->set('phone', '0512345678')
        ->set('password', 'قلم123')
        ->set('password_confirmation', 'قلم123')
        ->set('terms', true)
        ->call('register')
        ->assertHasNoErrors('password');

    expect(Student::where('email', 'short@example.com')->exists())->toBeTrue();
});

it('still asks for six', function () {
    Livewire::test(Register::class)
        ->set('name', 'طالب جديد')
        ->set('email', 'tooshort@example.com')
        ->set('phone', '0512345678')
        ->set('password', 'قلم12')
        ->set('password_confirmation', 'قلم12')
        ->set('terms', true)
        ->call('register')
        ->assertHasErrors('password');

    expect(Student::where('email', 'tooshort@example.com')->exists())->toBeFalse();
});

it('says the rule in the field, before it refuses anybody', function () {
    $this->get(route('register'))->assertSee('٦ أحرف على الأقل');
});
