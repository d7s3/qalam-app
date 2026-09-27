<?php

use App\Models\Form;
use App\Models\FormResponse;
use App\Support\NabighExam;
use App\Support\SurveyFieldTypes;
use Database\Seeders\NabighExamGrades1To2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Grades 1-2 have no self-report questionnaire and no situational-judgement
 * test in the printed guide — a six- or seven-year-old cannot answer either
 * reliably — so every form this seeder makes is filled by an adult: an
 * examiner during a live one-to-one session, a teacher, an observer watching
 * a group task. See `Database\Seeders\NabighExamGrades1To2Seeder` for why
 * «البناء المكاني» and «نابغة المصغر» are not among them.
 */
it('seeds all five examiner-facing forms, each open to the public with its own link', function () {
    $this->seed(NabighExamGrades1To2Seeder::class);

    $slugs = [
        'nabigh-1-2-session-a', 'nabigh-1-2-session-b',
        'nabigh-1-2-executive-function', 'nabigh-1-2-teacher-card', 'nabigh-1-2-teamwork-card',
    ];

    foreach ($slugs as $slug) {
        $form = Form::where('slug', $slug)->firstOrFail();
        expect($form->isOpenToPublic())->toBeTrue("{$slug} is not open to the public")
            ->and($form->is_supervisor_shared)->toBeTrue();
    }

    expect(Form::where('slug', 'nabigh-1-2-session-a')->first()->public_token)
        ->not->toBe(Form::where('slug', 'nabigh-1-2-session-b')->first()->public_token);
});

it('gives every mcq item in the relations task a correct option among its own choices', function () {
    $this->seed(NabighExamGrades1To2Seeder::class);

    foreach (['nabigh-1-2-session-a', 'nabigh-1-2-session-b'] as $slug) {
        $form = Form::where('slug', $slug)->firstOrFail();

        foreach (collect($form->fields)->where('type', 'mcq') as $field) {
            expect(in_array($field['correct_option'], $field['options'], true))
                ->toBeTrue("missing correct option: {$field['label']} ({$slug})");
        }
    }
});

/**
 * The session form is the one place the two batteries collide: it only ever
 * tags a field `reasoning` or `readiness`, the two dimension names grades 3-6
 * also uses. If battery detection ever went back to guessing from dimension
 * names alone, this is the form that would silently score against the wrong
 * weights — 25/15/15/10 instead of 25/15/20/15/10 — without any error.
 */
it('scores the individual session against grades 1-2\'s own weights, not grades 3-6\'s', function () {
    $this->seed(NabighExamGrades1To2Seeder::class);
    $form = Form::where('slug', 'nabigh-1-2-session-a')->firstOrFail();

    expect(NabighExam::batteryFor($form))->toBe(NabighExam::GRADES_1_TO_2);

    $dimensions = collect($form->fields)->pluck('dimension')->filter()->unique()->values()->sort()->values();
    expect($dimensions->all())->toBe(['readiness', 'reasoning']);
});

it('marks the classification, dynamic-learning and problem-solving tasks for a human, and the relations task for the key', function () {
    $this->seed(NabighExamGrades1To2Seeder::class);
    $form = Form::where('slug', 'nabigh-1-2-session-a')->firstOrFail();

    $reasoning = collect($form->fields)->where('dimension', 'reasoning');

    expect($reasoning->where('type', 'mcq')->count())->toBe(4) // A3 — العلاقات
        ->and($reasoning->where('type', 'text')->count())->toBe(4) // A1 — الأنماط
        ->and($reasoning->where('type', 'long_text')->where('max_manual_score', 2)->count())->toBe(4) // A2
        // A4 (5 indicators) + A5 (4 indicators), each its own 0-2 wording.
        ->and($reasoning->where('type', 'likert')->count())->toBe(9);

    foreach ($reasoning->where('type', 'likert') as $field) {
        expect(SurveyFieldTypes::scaleBounds($field))->toBe(['min' => 0, 'max' => 2]);
    }
});

it('lets an examiner fill the session as a live form, and computes what it can score on its own', function () {
    $this->seed(NabighExamGrades1To2Seeder::class);
    $form = Form::where('slug', 'nabigh-1-2-session-a')->firstOrFail();

    $screen = Livewire::test('public.apply', ['token' => $form->public_token]);

    foreach ($form->fields as $field) {
        if ($field['type'] === 'section') {
            continue;
        }

        $key = "answers.{$field['id']}";
        $screen->set($key, match (true) {
            $field['type'] === 'mcq' => $field['correct_option'],
            $field['type'] === 'likert' => $field['scale_max'],
            $field['type'] === 'select' => $field['options'][0],
            $field['type'] === 'yesno' => 'نعم',
            default => str_contains($field['label'], 'جوال') ? '0501234567' : 'ما قاله الطفل، بخط المقيّم.',
        });
    }

    $screen->call('submit')->assertHasNoErrors()->assertSet('done', true);

    $response = FormResponse::where('form_id', $form->id)->firstOrFail();

    expect($response->score['sections']['reasoning']['earned'])
        ->toBe($response->score['sections']['reasoning']['possible'])
        ->and($response->score['sections']['readiness']['manual_possible'])->toBeGreaterThan(0.0);
});

it('names five domains for grades 1-2, none of them the situational-judgement test grades 3-6 has', function () {
    expect(array_keys(NabighExam::GRADES_1_TO_2))->not->toContain('sjt')
        ->and(array_keys(NabighExam::GRADES_1_TO_2))->not->toContain('self_regulation')
        ->and(array_sum(NabighExam::weightsFor(NabighExam::GRADES_1_TO_2)))->toBe(85)
        // The remaining 15% is «نابغة المصغر», which has no rating card to digitise.
        ->and(array_keys(NabighExam::GRADES_1_TO_2))->toBe(['reasoning', 'readiness', 'executive_function', 'teacher', 'teamwork']);
});

it('keeps every form\'s field ids stable across a second run', function () {
    $this->seed(NabighExamGrades1To2Seeder::class);

    $before = collect(Form::where('slug', 'nabigh-1-2-teacher-card')->firstOrFail()->fields)->pluck('id');

    $this->seed(NabighExamGrades1To2Seeder::class);
    $after = collect(Form::where('slug', 'nabigh-1-2-teacher-card')->firstOrFail()->fields)->pluck('id');

    expect($after->all())->toBe($before->all());
});
