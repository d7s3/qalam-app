<?php

use App\Models\Form;
use App\Services\FormScoringService;
use App\Support\SurveyFieldTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * What turns a plain Form into something this service can grade is a single
 * tag — `dimension` — read off its own fields. Nothing else about a form has
 * to declare itself scored, and a form with no tagged field is left alone
 * entirely, which is what keeps every existing survey unaffected by this.
 */
function scoredForm(array $fields): Form
{
    return Form::create([
        'title' => 'اختبار مُصحَّح',
        'slug' => 'scored-'.Str::random(8),
        'color' => '#1B9A8F',
        'fields' => $fields,
    ]);
}

it('has no scoring dimension until a field is tagged with one', function () {
    $plain = scoredForm([
        ['id' => 'q1', 'type' => 'select', 'label' => 'رضاك؟', 'required' => false, 'options' => ['نعم', 'لا']],
    ]);

    expect(FormScoringService::isScored($plain))->toBeFalse();

    $scored = scoredForm([
        ['id' => 'q1', 'type' => 'mcq', 'label' => '2+2=؟', 'required' => true, 'options' => ['3', '4'], 'correct_option' => '4', 'points' => 1, 'dimension' => 'math'],
    ]);

    expect(FormScoringService::isScored($scored))->toBeTrue();
});

it('awards an mcq its points only on the exact correct option', function () {
    $form = scoredForm([
        ['id' => 'q1', 'type' => 'mcq', 'label' => '2+2=؟', 'required' => true, 'options' => ['3', '4', '5'], 'correct_option' => '4', 'points' => 2, 'dimension' => 'math'],
    ]);

    expect(FormScoringService::score($form, ['q1' => '4'])['sections']['math'])
        ->toBe(['earned' => 2.0, 'possible' => 2.0, 'manual_possible' => 0.0]);

    expect(FormScoringService::score($form, ['q1' => '3'])['sections']['math']['earned'])->toBe(0.0);
    expect(FormScoringService::score($form, [])['sections']['math']['earned'])->toBe(0.0);

    // The possible total is owed whether or not the question was answered —
    // an empty response should read as a low score, not as an easier test.
    expect(FormScoringService::score($form, [])['sections']['math']['possible'])->toBe(2.0);
});

it('reverses a likert item exactly the field says to, on its own scale', function () {
    $form = scoredForm([
        ['id' => 'straight', 'type' => 'likert', 'label' => 'أحب التعلم', 'required' => true, 'options' => [], 'scale_min' => 1, 'scale_max' => 4, 'dimension' => 'self_regulation'],
        ['id' => 'reversed', 'type' => 'likert', 'label' => 'أتشتت بسرعة', 'required' => true, 'options' => [], 'scale_min' => 1, 'scale_max' => 4, 'reverse_scored' => true, 'dimension' => 'self_regulation'],
    ]);

    // Both answered at 4 — the reversed item's 4 (the worst self-report) must
    // land as a 1, the lowest possible, not as another top mark.
    $sections = FormScoringService::score($form, ['straight' => 4, 'reversed' => 4])['sections'];

    expect($sections['self_regulation'])->toBe(['earned' => 5.0, 'possible' => 8.0, 'manual_possible' => 0.0]);
});

it('tracks a free-text item\'s ceiling as manual, and never guesses its score', function () {
    $form = scoredForm([
        ['id' => 'essay', 'type' => 'long_text', 'label' => 'اشرح إجابتك', 'required' => true, 'options' => [], 'dimension' => 'readiness', 'max_manual_score' => 3],
    ]);

    $score = FormScoringService::score($form, ['essay' => 'جواب طويل ومفصّل']);

    expect($score['sections']['readiness'])->toBe(['earned' => 0.0, 'possible' => 0.0, 'manual_possible' => 3.0]);
});

it('folds a grader\'s manual scores into the auto sections without double-counting', function () {
    $form = scoredForm([
        ['id' => 'mcq1', 'type' => 'mcq', 'label' => 'س', 'required' => true, 'options' => ['أ', 'ب'], 'correct_option' => 'ب', 'points' => 1, 'dimension' => 'reasoning'],
        ['id' => 'essay', 'type' => 'long_text', 'label' => 'اشرح', 'required' => true, 'options' => [], 'dimension' => 'readiness', 'max_manual_score' => 4],
    ]);

    $auto = FormScoringService::score($form, ['mcq1' => 'ب', 'essay' => 'نص']);
    $combined = FormScoringService::withManualGrades($form, $auto, ['essay' => 3]);

    expect($combined['reasoning'])->toBe(['earned' => 1.0, 'possible' => 1.0, 'manual_possible' => 0.0])
        ->and($combined['readiness'])->toBe(['earned' => 3.0, 'possible' => 4.0, 'manual_possible' => 4.0]);
});

it('weighs a composite across sections and skips one nothing answered yet', function () {
    $sections = [
        'reasoning' => ['earned' => 10.0, 'possible' => 20.0], // 50%
        'sjt' => ['earned' => 10.0, 'possible' => 10.0],       // 100%
        'readiness' => ['earned' => 0.0, 'possible' => 0.0],   // untouched — excluded, not a zero
    ];

    // (50*25 + 100*10) / (25+10) = 2250/35 = 64.3
    expect(FormScoringService::weightedComposite($sections, ['reasoning' => 25, 'sjt' => 10, 'readiness' => 15]))
        ->toBe(64.3);

    expect(FormScoringService::weightedComposite([], ['reasoning' => 25]))->toBeNull();
});

it('bounds a likert to its own scale, not the app\'s default 1-5', function () {
    $default = ['id' => 'q', 'type' => 'likert', 'label' => 'ل'];
    $custom = ['id' => 'q', 'type' => 'likert', 'label' => 'ل', 'scale_min' => 1, 'scale_max' => 4];

    expect(SurveyFieldTypes::scaleBounds($default))->toBe(['min' => 1, 'max' => 5])
        ->and(SurveyFieldTypes::scaleBounds($custom))->toBe(['min' => 1, 'max' => 4]);
});

it('reads a likert\'s own wording when the question carries it, and the default otherwise', function () {
    $custom = ['scale_labels' => [1 => 'لا يشبهني', 4 => 'يشبهني جدًا']];

    expect(SurveyFieldTypes::likertLabelsFor($custom))->toBe([1 => 'لا يشبهني', 4 => 'يشبهني جدًا'])
        ->and(SurveyFieldTypes::likertLabelsFor([]))->toBe(SurveyFieldTypes::likertScale());
});

it('knows the mcq type the way it knows every other question type', function () {
    expect(SurveyFieldTypes::exists('mcq'))->toBeTrue()
        ->and(SurveyFieldTypes::hasOptions('mcq'))->toBeTrue()
        ->and(SurveyFieldTypes::isLayout('mcq'))->toBeFalse()
        ->and(SurveyFieldTypes::defaultsFor('mcq'))->toBe(['options' => [], 'correct_option' => null, 'points' => 1, 'dimension' => null]);
});
