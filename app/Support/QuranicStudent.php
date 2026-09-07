<?php

namespace App\Support;

use App\Models\Circle;
use App\Models\User;

/**
 * The student of a Quranic circle, and the pages he alone is shown.
 *
 * The application was built when every student was a memoriser, so the mushaf,
 * the hifz and the review sat in every student's navigation whether he was in a
 * حلقة or not. The academy's centre has moved: the self programme is what a
 * student has by default, and the Quranic interface is what a حلقة adds on top.
 *
 * So this is the mirror of `RecitationOnlyTeacher`. There, a teacher of Quranic
 * circles is narrowed to the memorisation; here, a student is widened into it —
 * and both read the same flag, `circles.is_quranic`, so the academy marks its
 * circles once and two rules follow.
 *
 * A student in no circle at all is not a Quranic student: nothing has placed
 * him anywhere, and the self programme is what he has.
 */
class QuranicStudent
{
    /** Where the request remembers the answer, which the sidebar asks often. */
    private const CACHE = 'quranic_student';

    /** The container keys this class has bound, so it can let go of them all. */
    private static array $cached = [];

    /**
     * The pages that only mean something inside a حلقة.
     *
     * Kept as a list rather than inferred, so what a student outside one is not
     * shown is a decision somebody can read. The exams are not here on purpose:
     * an exam is not necessarily a memorisation exam, and withholding it would
     * take from students it was never about.
     */
    public const QURANIC_ONLY = [
        'student.plan',
        'student.hifz',
        'student.review',
        'student.plan-creator',
    ];

    /** Whether this student sits in a circle whose content is Quranic. */
    public static function applies(?User $user): bool
    {
        if (! $user?->circle_id) {
            return false;
        }

        $key = self::CACHE.":{$user->id}";

        if (! app()->bound($key)) {
            self::$cached[$key] = true;
            app()->instance($key, ['is' => (bool) Circle::whereKey($user->circle_id)->value('is_quranic')]);
        }

        return app($key)['is'];
    }

    /** Whether a page is one this student is not shown. */
    public static function withholds(?User $user, string $routeName): bool
    {
        return in_array($routeName, self::QURANIC_ONLY, true) && ! self::applies($user);
    }

    /** Forget one person's answer, or everybody's. */
    public static function forget(?int $userId = null): void
    {
        if ($userId !== null) {
            $key = self::CACHE.":{$userId}";
            app()->forgetInstance($key);
            unset(self::$cached[$key]);

            return;
        }

        foreach (array_keys(self::$cached) as $key) {
            app()->forgetInstance($key);
        }

        self::$cached = [];
    }
}
