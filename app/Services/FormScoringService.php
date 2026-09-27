<?php

namespace App\Services;

use App\Models\Form;
use App\Support\SurveyFieldTypes;

/**
 * What a scored form's answers are worth, read off the fields themselves.
 *
 * A plain survey's fields carry no `dimension`; a scored assessment (an mcq
 * with a `correct_option`, a likert with `reverse_scored`, a free-text answer
 * with a `max_manual_score`) tags each field with the domain it counts toward.
 * That single tag is what turns a generic Form into something this service can
 * grade — nothing else about the form needs to know it is being scored.
 */
class FormScoringService
{
    /** Whether any field on this form carries a scoring dimension at all. */
    public static function isScored(Form $form): bool
    {
        foreach ($form->fields ?? [] as $field) {
            if (! empty($field['dimension'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The auto-gradable share of a submission: every mcq compared to its
     * `correct_option`, every reverse-aware likert summed on its own scale.
     * A free-text field that carries a `max_manual_score` adds to
     * `manual_possible` so the review screen can say how much is still
     * waiting on a human, without ever guessing at its value here.
     *
     * @param  array<string, mixed>  $answers
     * @return array{sections: array<string, array{earned: float, possible: float, manual_possible: float}>}
     */
    public static function score(Form $form, array $answers): array
    {
        $sections = [];

        foreach ($form->fields ?? [] as $field) {
            $dimension = $field['dimension'] ?? null;
            if ($dimension === null) {
                continue;
            }

            $sections[$dimension] ??= ['earned' => 0.0, 'possible' => 0.0, 'manual_possible' => 0.0];
            $answer = $answers[$field['id']] ?? null;

            match ($field['type'] ?? '') {
                'mcq' => self::scoreMcq($sections[$dimension], $field, $answer),
                'likert' => self::scoreLikert($sections[$dimension], $field, $answer),
                default => self::trackManual($sections[$dimension], $field),
            };
        }

        return ['sections' => $sections];
    }

    /** @param array{earned: float, possible: float, manual_possible: float} $section */
    private static function scoreMcq(array &$section, array $field, mixed $answer): void
    {
        $points = (float) ($field['points'] ?? 1);
        $section['possible'] += $points;

        if ($answer !== null && $answer !== '' && $answer === ($field['correct_option'] ?? null)) {
            $section['earned'] += $points;
        }
    }

    /** @param array{earned: float, possible: float, manual_possible: float} $section */
    private static function scoreLikert(array &$section, array $field, mixed $answer): void
    {
        $bounds = SurveyFieldTypes::scaleBounds($field) ?? ['min' => 1, 'max' => 5];
        $section['possible'] += $bounds['max'];

        if ($answer === null || $answer === '') {
            return;
        }

        $value = (int) $answer;
        if ($field['reverse_scored'] ?? false) {
            $value = $bounds['min'] + $bounds['max'] - $value;
        }

        $section['earned'] += $value;
    }

    /** @param array{earned: float, possible: float, manual_possible: float} $section */
    private static function trackManual(array &$section, array $field): void
    {
        if ($max = $field['max_manual_score'] ?? null) {
            $section['manual_possible'] += (float) $max;
        }
    }

    /**
     * The auto sections with a human's grading folded in, so the review screen
     * has one set of totals rather than two it has to add itself each time.
     *
     * @param  array{sections: array<string, array{earned: float, possible: float, manual_possible: float}>}|null  $autoScore
     * @param  array<string, float>  $manualScores  field id => points a grader awarded
     * @return array<string, array{earned: float, possible: float, manual_possible: float}>
     */
    public static function withManualGrades(Form $form, ?array $autoScore, array $manualScores): array
    {
        $sections = $autoScore['sections'] ?? [];

        foreach ($form->fields ?? [] as $field) {
            $dimension = $field['dimension'] ?? null;
            $max = $field['max_manual_score'] ?? null;

            if ($dimension === null || $max === null) {
                continue;
            }

            $sections[$dimension] ??= ['earned' => 0.0, 'possible' => 0.0, 'manual_possible' => 0.0];
            $sections[$dimension]['earned'] += (float) ($manualScores[$field['id']] ?? 0);
            $sections[$dimension]['possible'] += (float) $max;
        }

        return $sections;
    }

    /**
     * One 0-100 figure across several sections, each weighed the way the
     * instrument weighs its own domains — a section with nothing answered yet
     * is left out rather than counted as a zero, so an unfinished response
     * does not read as a failed one.
     *
     * @param  array<string, array{earned: float, possible: float}>  $sections
     * @param  array<string, float>  $weights  dimension => weight
     */
    public static function weightedComposite(array $sections, array $weights): ?float
    {
        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($weights as $dimension => $weight) {
            $section = $sections[$dimension] ?? null;
            if (! $section || $section['possible'] <= 0) {
                continue;
            }

            $weightedSum += ($section['earned'] / $section['possible'] * 100) * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : null;
    }
}
