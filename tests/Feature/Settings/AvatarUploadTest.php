<?php

use App\Livewire\Settings\Profile;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('lets a student add a profile picture from the settings page', function () {
    $student = Student::factory()->create();
    $this->actingAs($student, 'student');

    Livewire::test('student.settings')
        ->set('avatarFile', UploadedFile::fake()->image('me.jpg'))
        ->assertHasNoErrors();

    $path = $student->fresh()->avatar_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('lets a student replace their profile picture and removes the old file', function () {
    $student = Student::factory()->create();
    $this->actingAs($student, 'student');

    $component = Livewire::test('student.settings')
        ->set('avatarFile', UploadedFile::fake()->image('first.jpg'));

    $firstPath = $student->fresh()->avatar_path;
    Storage::disk('public')->assertExists($firstPath);

    $component->set('avatarFile', UploadedFile::fake()->image('second.jpg'));

    $secondPath = $student->fresh()->avatar_path;
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertExists($secondPath);
    Storage::disk('public')->assertMissing($firstPath);
});

it('lets a student delete their profile picture', function () {
    $student = Student::factory()->create();
    $this->actingAs($student, 'student');

    $component = Livewire::test('student.settings')
        ->set('avatarFile', UploadedFile::fake()->image('me.jpg'));

    $path = $student->fresh()->avatar_path;
    Storage::disk('public')->assertExists($path);

    $component->call('deleteAvatar');

    expect($student->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects a non-image file as a student avatar', function () {
    $student = Student::factory()->create();
    $this->actingAs($student, 'student');

    Livewire::test('student.settings')
        ->set('avatarFile', UploadedFile::fake()->create('resume.pdf', 100))
        ->assertHasErrors('avatarFile');

    expect($student->fresh()->avatar_path)->toBeNull();
});

it('lets a teacher add and delete a profile picture via the shared settings page', function () {
    $teacher = Teacher::factory()->create();
    $this->actingAs($teacher, 'teacher');

    Livewire::test(Profile::class)
        ->set('avatarFile', UploadedFile::fake()->image('me.jpg'))
        ->assertHasNoErrors();

    $path = $teacher->fresh()->avatar_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    Livewire::test(Profile::class)
        ->call('deleteAvatar');

    expect($teacher->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('renders the student settings page (with header) without errors after setting an avatar', function () {
    $student = Student::factory()->create();
    $this->actingAs($student, 'student');

    Livewire::test('student.settings')
        ->set('avatarFile', UploadedFile::fake()->image('me.jpg'));

    $this->get(route('student.settings'))->assertOk();
});

it('renders the teacher profile settings page (with header) without errors after setting an avatar', function () {
    $teacher = Teacher::factory()->create();
    $this->actingAs($teacher, 'teacher');

    Livewire::test(Profile::class)
        ->set('avatarFile', UploadedFile::fake()->image('me.jpg'));

    $this->get(route('profile.edit'))->assertOk();
});
