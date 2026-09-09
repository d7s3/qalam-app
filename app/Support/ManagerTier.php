<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserRole;

/**
 * How far a manager's office reaches, and what he is called for reaching it.
 *
 * The academy runs on برنامج ← دفعة, and it wants a manager at each level: one
 * over the centre, one over a programme, one over a cohort. They are the same
 * office differing only in how much of the academy it covers, so they are not
 * three roles: they are the manager's role with a reach written on the holding,
 * which `Scope` has always supported.
 *
 * This was built once as a single tier called «مدير الدفعة», which was wrong —
 * it answered the same name whether the man held a programme or one cohort
 * inside it, and those are two different offices in the academy's own words.
 * The reach already distinguished them; only the name did not.
 *
 * Everything below the centre keeps the manager's screens and loses the
 * centre's own, and sees only what its reach covers.
 */
class ManagerTier
{
    /** The whole academy: every programme, every cohort, every person. */
    public const CENTRE = 'centre';

    /** One programme or more, whole — its cohorts, their people, their work. */
    public const PROGRAMME = 'programme';

    /** Named cohorts inside a programme, and nothing beside them. */
    public const COHORT = 'cohort';

    /** What each is called, wherever a person's office is written. */
    public const LABELS = [
        self::CENTRE => 'مدير المركز',
        self::PROGRAMME => 'مدير البرنامج',
        self::COHORT => 'مدير الدفعة',
    ];

    /** Where the request remembers the answer, which the sidebar asks often. */
    private const CACHE = 'manager_tier';

    /** The container keys this class has bound, so it can let go of them all. */
    private static array $cached = [];

    /**
     * The centre's own pages, which are not a programme's business.
     *
     * A manager below the centre runs what he was given; he does not take the
     * database's backups, decide which roles exist, hand out other people's
     * reaches, make managers, or create and delete programmes — those belong to
     * the centre as a whole, and a man over one programme holding them would be
     * over the centre in everything but name.
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
        'manager.managers',
        'manager.stages',
        'manager.backup-browser',
        'manager.backup.download',
        'manager.ai-settings',
        'manager.whatsapp-settings',
    ];

    /**
     * Which of the three this person is.
     *
     * A super administrator is the centre's, whatever is written on his holding:
     * his mark is answered before anything else asks, so saying otherwise here
     * would only put a name on screen that nothing else obeys.
     */
    public static function of(?User $user): string
    {
        if (! $user || $user->is_super_admin) {
            return self::CENTRE;
        }

        $key = self::CACHE.":{$user->id}";

        if (! app()->bound($key)) {
            self::$cached[$key] = true;

            $holding = $user->roles->firstWhere('role', 'manager');
            $ids = $holding?->scope_ids ?? [];

            app()->instance($key, match (true) {
                $ids === [] => self::CENTRE,
                $holding?->scope_type === UserRole::SCOPE_STAGES => self::PROGRAMME,
                $holding?->scope_type === UserRole::SCOPE_CIRCLES => self::COHORT,
                default => self::CENTRE,
            });
        }

        return app($key);
    }

    /** What to call him. */
    public static function label(?User $user): string
    {
        return self::LABELS[self::of($user)];
    }

    /** Whether his office covers less than the academy. */
    public static function isNarrowed(?User $user): bool
    {
        return self::of($user) !== self::CENTRE;
    }

    /** Whether the centre keeps this page from him. */
    public static function withholds(?User $user, string $routeName): bool
    {
        return self::isNarrowed($user) && self::covers($routeName);
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
