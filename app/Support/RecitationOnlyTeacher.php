<?php

namespace App\Support;

use App\Models\Circle;
use App\Models\User;

/**
 * The teacher of a Quranic circle, and the narrower view that goes with it.
 *
 * A حلقة and a دفعة sit at the same level and differ in that the حلقة's content
 * is Quranic. Its teacher is there for the memorisation and the review, and the
 * academy's rule is that he sees those of his student and nothing else — not
 * the self programme's other fields, not the متون, not the marks.
 *
 * Which is where the distinction we drew earlier earns its keep: the ورد is one
 * figure the self programme carries, while الحفظ and المراجعة are the two the
 * memorisation plan grades separately. He works in the second pair.
 *
 * He is declared rather than inferred, and that is deliberate. The obvious rule
 * was to read it off his circles, but `is_quranic` was added with a default of
 * true so that nothing already running would change — so every circle in the
 * academy is Quranic today, and inferring it would have narrowed every teacher
 * at once. `suggests()` offers the reading; the flag on the teacher decides.
 */
class RecitationOnlyTeacher
{
    /** Where the request remembers the answer, which the sidebar asks often. */
    private const CACHE = 'recitation_only_teacher';

    /**
     * The pages that show a student's work other than his memorisation.
     *
     * Kept as a list rather than inferred, so what a Quranic teacher is not
     * shown is a decision somebody can read rather than a rule to reverse
     * engineer. Everything else he keeps: attendance, recitation, the mutual
     * recitation, the plans, his students, the Quranic discipline.
     */
    public const WITHHELD = [
        'teacher.self-program',
        'teacher.ode-plans',
        'teacher.grade-items',
        'teacher.forms',
        'teacher.reports.self-program',
        'teacher.reports.mutun',
        'teacher.reports.forms',
    ];

    /** Whether this person is a teacher of Quranic circles only. */
    public static function applies(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $key = self::CACHE.":{$user->id}";

        if (! app()->bound($key)) {
            app()->instance($key, ['is' => self::resolve($user)]);
        }

        return app($key)['is'];
    }

    /** Whether a page is one this teacher is not shown. */
    public static function withholds(?User $user, string $routeName): bool
    {
        return self::applies($user) && in_array($routeName, self::WITHHELD, true);
    }

    public static function forget(?int $userId = null): void
    {
        if ($userId !== null) {
            app()->forgetInstance(self::CACHE.":{$userId}");
        }
    }

    /**
     * What his circles suggest, for the screen that offers the designation.
     *
     * A teacher every one of whose cohorts is Quranic is probably one. A teacher
     * of both kinds is not — he needs the wider view for his other cohort — and
     * a teacher with no cohort yet has had nothing decided about him.
     */
    public static function suggests(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $circles = Circle::whereHas('teachers', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('is_quranic');

        return $circles->isNotEmpty() && $circles->every(fn ($quranic) => (bool) $quranic);
    }

    private static function resolve(User $user): bool
    {
        return (bool) $user->is_recitation_only;
    }
}
