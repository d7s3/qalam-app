<?php

use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Supervisor;
use App\Support\NabighExam;
use Database\Seeders\NabighExamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The electronic quarter of «مقياس نابغة»: reasoning, academic readiness, a
 * self-regulation questionnaire, and a situational-judgement test — the four
 * of the printed battery's seven a child can answer on a screen with a parent
 * beside him. See `App\Support\NabighExam` for why the other three stay on
 * paper, and `Database\Seeders\NabighExamSeeder` for why option order is
 * shuffled at seed time.
 */
it('seeds two parallel forms, each open to the public with its own link', function () {
    $this->seed(NabighExamSeeder::class);

    $a = Form::where('slug', 'nabigh-exam-a')->firstOrFail();
    $b = Form::where('slug', 'nabigh-exam-b')->firstOrFail();

    expect($a->isOpenToPublic())->toBeTrue()
        ->and($b->isOpenToPublic())->toBeTrue()
        ->and($a->public_token)->not->toBe($b->public_token)
        ->and($a->is_supervisor_shared)->toBeTrue();

    $dimensions = collect($a->fields)->pluck('dimension')->filter()->unique()->values();
    expect($dimensions->sort()->values()->all())->toBe(['readiness', 'reasoning', 'self_regulation', 'sjt']);
});

it('gives every mcq item a correct option that is actually among its own choices', function () {
    $this->seed(NabighExamSeeder::class);

    foreach (['nabigh-exam-a', 'nabigh-exam-b'] as $slug) {
        $form = Form::where('slug', $slug)->firstOrFail();

        foreach (collect($form->fields)->where('type', 'mcq') as $field) {
            expect(in_array($field['correct_option'], $field['options'], true))
                ->toBeTrue("لا توجد الإجابة الصحيحة ضمن خيارات: {$field['label']} ({$slug})");
        }
    }
});

/**
 * The source booklet placed Form B's intended situational-judgement answer in
 * the same letter (option ب) in all ten items — a pattern a child who has
 * seen enough multiple-choice tests, or a coached parent, could exploit
 * without reading a single scenario. Seeding shuffles each item's options
 * with its own seed, so the fix does not simply move the pattern to another
 * single letter.
 */
it('does not leave every sjt answer sitting in the same position', function () {
    $this->seed(NabighExamSeeder::class);

    $form = Form::where('slug', 'nabigh-exam-b')->firstOrFail();
    $positions = collect($form->fields)->where('dimension', 'sjt')
        ->map(fn (array $f) => array_search($f['correct_option'], $f['options'], true));

    expect($positions->unique()->count())->toBeGreaterThan(1);
});

it('keeps its field ids stable across a second run, so no answer is orphaned', function () {
    $this->seed(NabighExamSeeder::class);
    $before = collect(Form::where('slug', 'nabigh-exam-a')->firstOrFail()->fields)->pluck('id');

    $form = Form::where('slug', 'nabigh-exam-a')->firstOrFail();
    FormResponse::create([
        'form_id' => $form->id,
        'answers' => [$before->first() => 'قيمة'],
    ]);

    $this->seed(NabighExamSeeder::class);
    $after = collect(Form::where('slug', 'nabigh-exam-a')->firstOrFail()->fields)->pluck('id');

    expect($after->all())->toBe($before->all());
});

/** A full run through the public page, exactly as a parent would fill it in. */
function answerNabighExam(Form $form, bool $perfect = true)
{
    $screen = Livewire::test('public.apply', ['token' => $form->public_token]);

    foreach ($form->fields as $field) {
        if ($field['type'] === 'section') {
            continue;
        }

        $key = "answers.{$field['id']}";

        $screen->set($key, match (true) {
            $field['type'] === 'mcq' => $perfect ? $field['correct_option'] : $field['options'][0],
            $field['type'] === 'likert' => $perfect && ($field['reverse_scored'] ?? false) ? $field['scale_min'] : ($field['scale_max'] ?? 4),
            $field['type'] === 'select' => $field['options'][0],
            default => str_contains($field['label'], 'جوال') ? '0501234567' : 'إجابة الطالب',
        });
    }

    return $screen->call('submit');
}

it('scores a perfect run at 100 on every auto-graded section, and leaves readiness for a human', function () {
    $this->seed(NabighExamSeeder::class);
    $form = Form::where('slug', 'nabigh-exam-a')->firstOrFail();

    answerNabighExam($form, perfect: true)->assertHasNoErrors()->assertSet('done', true);

    $response = FormResponse::where('form_id', $form->id)->firstOrFail();
    $sections = $response->score['sections'];

    expect($sections['reasoning']['earned'])->toBe($sections['reasoning']['possible'])
        ->and($sections['sjt']['earned'])->toBe($sections['sjt']['possible'])
        ->and($sections['self_regulation']['earned'])->toBe($sections['self_regulation']['possible'])
        ->and((float) $sections['readiness']['possible'])->toBe(0.0)
        ->and($sections['readiness']['manual_possible'])->toBeGreaterThan(0.0)
        ->and($response->graded_at)->toBeNull();
});

it('scores a wrong-on-purpose run below full marks on the graded sections', function () {
    $this->seed(NabighExamSeeder::class);
    $form = Form::where('slug', 'nabigh-exam-a')->firstOrFail();

    answerNabighExam($form, perfect: false)->assertHasNoErrors();

    $sections = FormResponse::where('form_id', $form->id)->firstOrFail()->score['sections'];

    expect($sections['reasoning']['earned'])->toBeLessThan($sections['reasoning']['possible']);
});

it('lets a supervisor grade the free-text readiness answers and folds them into the composite', function () {
    $this->seed(NabighExamSeeder::class);
    $form = Form::where('slug', 'nabigh-exam-a')->firstOrFail();

    answerNabighExam($form, perfect: true);
    $response = FormResponse::where('form_id', $form->id)->firstOrFail();

    $supervisor = Supervisor::factory()->create();
    $screen = Livewire::actingAs($supervisor, 'supervisor')
        ->test('supervisor.form-responses', ['formId' => $form->id])
        ->assertSuccessful();

    expect($screen->viewData('isScoredForm'))->toBeTrue();

    $manualFields = collect($screen->viewData('manualFields'));
    expect($manualFields)->not->toBeEmpty();

    $screen->call('openGradeModal', $response->id)->assertSet('showGradeModal', true);

    foreach ($manualFields as $field) {
        $screen->set("manualGradeInputs.{$field['id']}", $field['max_manual_score']);
    }

    $screen->call('saveManualGrades')->assertHasNoErrors();

    $response->refresh();
    expect($response->graded_at)->not->toBeNull()
        ->and($response->graded_by_id)->toBe($supervisor->id)
        ->and($response->graded_by_type)->toBe('supervisor');

    $summary = $screen->instance()->scoreSummary($response);
    expect($summary['composite'])->toBe(100.0);
});

it('refuses a manual grade above the ceiling the question itself declares', function () {
    $this->seed(NabighExamSeeder::class);
    $form = Form::where('slug', 'nabigh-exam-a')->firstOrFail();

    answerNabighExam($form, perfect: true);
    $response = FormResponse::where('form_id', $form->id)->firstOrFail();

    $supervisor = Supervisor::factory()->create();
    $field = collect($form->fields)->firstWhere('dimension', 'readiness');

    Livewire::actingAs($supervisor, 'supervisor')
        ->test('supervisor.form-responses', ['formId' => $form->id])
        ->call('openGradeModal', $response->id)
        ->set("manualGradeInputs.{$field['id']}", $field['max_manual_score'] + 10)
        ->call('saveManualGrades')
        ->assertHasErrors(["manualGradeInputs.{$field['id']}"]);

    expect($response->fresh()->graded_at)->toBeNull();
});

it('never scores a plain, untagged form — nothing about it changes', function () {
    $form = Form::create([
        'title' => 'استبانة رضا',
        'slug' => 'plain-survey',
        'color' => '#7a2727',
        'is_public' => true,
        'public_token' => Str::random(24),
        'status' => 'published',
        'published_at' => now(),
        'fields' => [
            ['id' => 'q', 'type' => 'select', 'label' => 'رضاك؟', 'required' => true, 'options' => ['نعم', 'لا']],
        ],
    ]);

    Livewire::test('public.apply', ['token' => $form->public_token])
        ->set('answers.q', 'نعم')
        ->call('submit')
        ->assertHasNoErrors();

    expect(FormResponse::where('form_id', $form->id)->firstOrFail()->score)->toBeNull();
});

it('names its four domains and their weights, summing to the battery\'s electronic share', function () {
    $battery = NabighExam::GRADES_3_TO_6;

    expect(array_keys(NabighExam::labelsFor($battery)))->toBe(array_keys(NabighExam::weightsFor($battery)))
        ->and(array_sum(NabighExam::weightsFor($battery)))->toBe(65);
});

it('tells the two batteries apart by slug, since grades 1-2\'s session form shares dimension names with grades 3-6\'s', function () {
    $gradeForm = fn (string $slug) => Form::make(['slug' => $slug]);

    expect(NabighExam::batteryFor($gradeForm('nabigh-exam-a')))->toBe(NabighExam::GRADES_3_TO_6)
        ->and(NabighExam::batteryFor($gradeForm('nabigh-1-2-session-a')))->toBe(NabighExam::GRADES_1_TO_2)
        // The session form's own dimensions (reasoning, readiness) are the two
        // names both batteries share — the slug is what settles it here.
        ->and(NabighExam::batteryFor($gradeForm('nabigh-1-2-executive-function')))->toBe(NabighExam::GRADES_1_TO_2);
});
