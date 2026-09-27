<?php

use App\Models\Form;
use App\Support\NabighExam;
use Database\Seeders\NabighExamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * One link a whole intake is given, that sorts each student to the instrument
 * their grade calls for. A family should not have to know which token to open,
 * which of two parallel forms their child sits, or which battery their grade
 * belongs to — the grade decides it, and this page asks only that.
 */
it('opens the landing to anyone with the link', function () {
    $this->get(route('nabigh'))
        ->assertSuccessful()
        ->assertSee('مقياس نابغة')
        ->assertSee('في أي صف الطالب؟');
});

it('sends a grades 3-6 student on to one of the two device exams', function () {
    $this->seed(NabighExamSeeder::class);

    Livewire::test('public.nabigh')
        ->call('choose', 'الرابع الابتدائي')
        ->assertRedirect(); // to one of the exams' /apply/{token}
});

it('only ever hands out an exam that is actually open', function () {
    $this->seed(NabighExamSeeder::class);

    // Both parallel forms closed: the grade is real, but there is nothing to
    // give, so the student is told so rather than dropped on a dead 410.
    Form::whereIn('slug', ['nabigh-exam-a', 'nabigh-exam-b'])->update(['is_public' => false]);

    Livewire::test('public.nabigh')
        ->call('choose', 'الخامس الابتدائي')
        ->assertSet('closed', true)
        ->assertNoRedirect();
});

it('tells a grades 1-2 family their assessment is a live session, and does not route the child into a form', function () {
    $this->seed(NabighExamSeeder::class);

    Livewire::test('public.nabigh')
        ->call('choose', 'الأول الابتدائي')
        ->assertSet('inPersonGrade', 'الأول الابتدائي')
        ->assertNoRedirect()
        ->assertSee('جلسة');
});

it('ignores a grade it does not offer', function () {
    Livewire::test('public.nabigh')
        ->call('choose', 'الثالث المتوسط')
        ->assertSet('inPersonGrade', null)
        ->assertSet('closed', false)
        ->assertNoRedirect();
});

it('lets a family back out of a result to pick another grade', function () {
    Livewire::test('public.nabigh')
        ->call('choose', 'الثاني الابتدائي')
        ->assertSet('inPersonGrade', 'الثاني الابتدائي')
        ->call('chooseAgain')
        ->assertSet('inPersonGrade', null)
        ->assertSet('closed', false);
});

it('keeps the grade map and the two device exams in agreement', function () {
    $this->seed(NabighExamSeeder::class);

    // Every self-administered grade points at slugs that were actually seeded,
    // so the map can never send a family to a form that does not exist.
    foreach (NabighExam::GRADE_ROUTES as $grade => $route) {
        if ($route['mode'] !== 'self') {
            continue;
        }

        foreach ($route['forms'] as $slug) {
            expect(Form::where('slug', $slug)->exists())->toBeTrue("mapped form missing: {$slug}");
        }

        expect(NabighExam::openExamForGrade($grade))->not->toBeNull("no open exam for {$grade}");
        expect(NabighExam::isInPersonGrade($grade))->toBeFalse();
    }
});
