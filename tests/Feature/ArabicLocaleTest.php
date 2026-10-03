<?php

use App\Livewire\Auth\Login;
use App\Livewire\Supervisor\Circles;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

/**
 * The academy reads Arabic, so what the framework says on its behalf — a
 * missing field, a wrong password — is said in Arabic too, and the page
 * declares the language it is written in.
 */
it('runs in Arabic', function () {
    expect(app()->getLocale())->toBe('ar');
});

it('says what a field is missing in Arabic, naming the field', function () {
    $stage = Stage::factory()->create();
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($stage->id);
    $this->actingAs($supervisor, 'supervisor');

    $circles = Livewire::test(Circles::class)
        ->call('create')
        ->set('stage_id', $stage->id)
        ->call('save')
        ->assertHasErrors('name');

    expect($circles->errors()->first('name'))->toBe('حقل الاسم مطلوب.');
});

it('agrees with a feminine field name', function () {
    expect(__('validation.required', ['attribute' => __('validation.attributes.password')]))
        ->toBe('حقل كلمة المرور مطلوب.');
});

it('refuses a wrong password in Arabic', function () {
    Livewire::test(Login::class)
        ->set('email', 'nobody@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
});

it('declares the page Arabic to the browser and screen readers', function () {
    $stage = Stage::factory()->create();
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($stage->id);

    $this->actingAs($supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false);
});

it('names the student\'s settings page in Arabic', function () {
    $student = Student::factory()->create(['status' => 'active', 'is_approved' => true]);

    $this->actingAs($student, 'student')
        ->get(route('student.settings'))
        ->assertOk()
        ->assertSee('<title>', false)
        ->assertSee('الإعدادات - ')
        ->assertDontSee('<title>Settings', false);
});

it('translates every English phrase the screens pass through __()', function () {
    $translations = json_decode(file_get_contents(lang_path('ar.json')), true);

    $phrases = collect(Finder::create()->files()->in([resource_path('views'), app_path()])->name('*.php'))
        ->flatMap(fn ($file) => preg_match_all("/__\\(['\"]([A-Z][A-Za-z ,.!?:-]{1,80})['\"]\\)/", $file->getContents(), $m) ? $m[1] : [])
        ->unique();

    expect($phrases->reject(fn ($phrase) => isset($translations[$phrase]))->values()->all())->toBe([]);
});
