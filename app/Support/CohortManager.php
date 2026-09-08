<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserRole;

/**
 * The tier between the centre's manager and a cohort's supervisor.
 *
 * The academy wanted somebody over a programme rather than over the academy: he
 * sees his programme whole — its cohorts, their supervisors, their teachers,
 * their students, everything scheduled in it — and sees nothing of the
 * programme next door.
 *
 * He is not a sixth guard, and deliberately. `Scope` has always let a reach be
 * written onto one person's holding of a role, and a manager's holding narrowed
 * to named programmes is exactly this tier — the reach, the queries and the
 * per-programme page grants all already answer for him. What was missing was
 * not the power but the name: he was titled «مدير» in every header, so nothing
 * on screen told him apart from the man over the whole centre.
 *
 * So this class is the tier's name and its one subtraction. Everything else he
 * has by being a manager, narrowed.
 */
class CohortManager
{
    /** What he is called, wherever a person's office is written. */
    public const LABEL = 'مدير الدفعة';

    /** Where the request remembers the answer, which the sidebar asks often. */
    private const CACHE = 'cohort_manager';

    /** The container keys this class has bound, so it can let go of them all. */
    private static array $cached = [];

    /**
     * The centre's own pages, which are not a programme's business.
     *
     * A programme's director runs his programme; he does not take the database's
     * backups, decide which roles exist, hand out other people's reaches, or
     * create and delete programmes — those belong to the centre as a whole, and
     * a man over one programme holding them would be over the centre in
     * everything but name.
     *
     * Kept as a list rather than inferred, so what he is not shown is a decision
     * somebody can read. And it is a narrowing his role cannot undo, like the
     * Quranic teacher's: a page the academy has not registered yet is open to
     * everyone, and this has to hold whether or not anybody has described the
     * page. To give a man these, make him a manager of the centre.
     */
    public const WITHHELD = [
        'manager.settings',
        'manager.role-permissions',
        'manager.user-access',
        'manager.staff-members',
        'manager.stages',
        'manager.backup-browser',
        'manager.backup.download',
        'manager.ai-settings',
        'manager.whatsapp-settings',
    ];

    /**
     * Whether this person holds the manager's role over programmes rather than
     * over the centre.
     *
     * A super administrator never is, whatever is written on his holding: his
     * mark is answered before anything else asks.
     */
    public static function is(?User $user): bool
    {
        if (! $user || $user->is_super_admin) {
            return false;
        }

        $key = self::CACHE.":{$user->id}";

        if (! app()->bound($key)) {
            self::$cached[$key] = true;

            $holding = $user->roles->firstWhere('role', 'manager');

            app()->instance($key, $holding !== null
                && in_array($holding->scope_type, [UserRole::SCOPE_STAGES, UserRole::SCOPE_CIRCLES], true)
                && $holding->scope_ids !== []);
        }

        return app($key);
    }

    /** Whether the centre keeps this page from him. */
    public static function withholds(?User $user, string $routeName): bool
    {
        return self::is($user) && self::covers($routeName);
    }

    /**
     * Whether a route is one of the centre's own.
     *
     * A page's children go with it — `manager.backup.download.store` is the
     * backup page's own doing — so the list names the page and the branch
     * follows, exactly as the screen grants are read.
     */
    public static function covers(string $routeName): bool
    {
        foreach (self::WITHHELD as $withheld) {
            if ($routeName === $withheld || str_starts_with($routeName, $withheld.'.')) {
                return true;
            }
        }

        return false;
    }

    /** Let go of what was remembered, for a test that changes a holding. */
    public static function forget(): void
    {
        foreach (array_keys(self::$cached) as $key) {
            app()->forgetInstance($key);
        }

        self::$cached = [];
    }
}
