<?php

namespace App\Support;

use App\Models\Form;

/**
 * The domains of «مقياس نابغة» a device can actually administer, for each age
 * band the printed battery ships as a separate booklet.
 *
 * Grades 3-6 has seven sections; four are self-report or auto-scorable and
 * travel to a screen (reasoning, readiness, self-regulation, a situational
 * judgement test). Grades 1-2 has six sections and a different shape: a child
 * that age cannot self-report reliably, so there is no questionnaire or SJT —
 * instead an examiner runs a live 45-60 minute session and records what she
 * observes, a teacher rates separately, and an observer rates a group task.
 * All three of those are still short structured rating forms, so — unlike the
 * older grades — every domain but «نابغة المصغر» (a schedule, not a rubric)
 * gets digitised here.
 *
 * Each battery is read off `FormScoringService`'s own `dimension` tags, so a
 * form declares which battery it belongs to simply by which dimensions its
 * fields carry — nothing stores that choice separately.
 */
class NabighExam
{
    /**
     * @var array<string, array{label: string, weight: int}>
     *
     * Weights are the printed guide's own (grades 3-6 §2), out of the four
     * sections a device covers: 25+15+15+10 of the battery's 100.
     */
    public const GRADES_3_TO_6 = [
        'reasoning' => ['label' => 'الاستدلال والقدرة على التعلم', 'weight' => 25],
        'readiness' => ['label' => 'الجاهزية الأكاديمية', 'weight' => 15],
        'self_regulation' => ['label' => 'التنظيم الذاتي والدافعية', 'weight' => 15],
        'sjt' => ['label' => 'الحكم الموقفي (SJT)', 'weight' => 10],
    ];

    /**
     * @var array<string, array{label: string, weight: int}>
     *
     * Weights are the printed guide's own (grades 1-2 §2): 25+15+20+15+10 of
     * the battery's 100. «نابغة المصغر» carries the remaining 15% but ships no
     * rating card in the source guide — a schedule of what to watch for, not
     * a rubric — so it has no `dimension` and stays off this list entirely.
     */
    public const GRADES_1_TO_2 = [
        'reasoning' => ['label' => 'الاستدلال وقابلية التعلم', 'weight' => 25],
        'readiness' => ['label' => 'الجاهزية الأساسية', 'weight' => 15],
        'executive_function' => ['label' => 'التنظيم والوظائف التنفيذية', 'weight' => 20],
        'teacher' => ['label' => 'تقييم المعلم', 'weight' => 15],
        'teamwork' => ['label' => 'التفاعل الجماعي', 'weight' => 10],
    ];

    /**
     * How each grade is assessed, for the one landing page that routes a
     * student to the right instrument.
     *
     * Grades 3-6 are self-administered on the device: the student answers one
     * of two parallel forms (A or B), and offering both is what stops a sibling
     * or a friend tested the same day from simply repeating the answers. Grades
     * 1-2 are `in_person` — a six- or seven-year-old cannot self-report, so
     * there is nothing for the child to do on a screen; the landing hands those
     * families the next step rather than routing a small child into an
     * examiner's rubric they cannot fill.
     *
     * @var array<string, array{mode: string, forms?: array<int, string>}>
     */
    public const GRADE_ROUTES = [
        'الأول الابتدائي' => ['mode' => 'in_person'],
        'الثاني الابتدائي' => ['mode' => 'in_person'],
        'الثالث الابتدائي' => ['mode' => 'self', 'forms' => ['nabigh-exam-a', 'nabigh-exam-b']],
        'الرابع الابتدائي' => ['mode' => 'self', 'forms' => ['nabigh-exam-a', 'nabigh-exam-b']],
        'الخامس الابتدائي' => ['mode' => 'self', 'forms' => ['nabigh-exam-a', 'nabigh-exam-b']],
        'السادس الابتدائي' => ['mode' => 'self', 'forms' => ['nabigh-exam-a', 'nabigh-exam-b']],
    ];

    /**
     * The open exam a self-administered grade should be handed, chosen at
     * random between the parallel forms so no two students in a row are given
     * the same one. Null when the grade is not self-administered or no form is
     * currently open — the caller decides what to say in each case.
     */
    public static function openExamForGrade(string $grade): ?Form
    {
        $route = self::GRADE_ROUTES[$grade] ?? null;

        if (! $route || $route['mode'] !== 'self') {
            return null;
        }

        return Form::whereIn('slug', $route['forms'])
            ->get()
            ->filter(fn (Form $form) => $form->isOpenToPublic())
            ->shuffle()
            ->first();
    }

    /** Whether a grade is assessed in a live session rather than on the device. */
    public static function isInPersonGrade(string $grade): bool
    {
        return (self::GRADE_ROUTES[$grade]['mode'] ?? null) === 'in_person';
    }

    /**
     * Which battery a form belongs to.
     *
     * `reasoning` and `readiness` are the two dimension names both age bands
     * share, so grades 1-2's individual-session form — which carries only
     * those two — cannot be told apart from grades 3-6's by its dimensions
     * alone; the slug both seeders give their forms is the one signal that
     * never collides, so it settles this rather than a guess at the fields.
     */
    public static function batteryFor(Form $form): array
    {
        return str_starts_with($form->slug, 'nabigh-1-2-')
            ? self::GRADES_1_TO_2
            : self::GRADES_3_TO_6;
    }

    /**
     * @param  array<string, array{label: string, weight: int}>  $battery
     * @return array<string, string>
     */
    public static function labelsFor(array $battery): array
    {
        return array_map(fn (array $domain) => $domain['label'], $battery);
    }

    /**
     * @param  array<string, array{label: string, weight: int}>  $battery
     * @return array<string, int>
     */
    public static function weightsFor(array $battery): array
    {
        return array_map(fn (array $domain) => $domain['weight'], $battery);
    }

    /** @param array<string, array{label: string, weight: int}> $battery */
    public static function label(string $dimension, array $battery): string
    {
        return $battery[$dimension]['label'] ?? $dimension;
    }
}
