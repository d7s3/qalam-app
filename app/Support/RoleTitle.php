<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * What to call a person, in one place.
 *
 * Three files carried their own map of role to Arabic word — two headers and a
 * report — so renaming a role in the roles table renamed it everywhere except
 * on the screen the person actually looks at, and no map knew that a manager
 * held over one programme is not the manager of the centre.
 *
 * The roles table is the source: the academy renames its offices there and the
 * new name appears wherever anybody is named. The one thing added on top is the
 * tier that has no row of its own — the cohort's manager, who is the manager's
 * role narrowed to programmes.
 */
class RoleTitle
{
    private const CACHE = 'role_titles';

    /**
     * The office a person holds, as it should be written.
     *
     * Falls back to the role's key when the academy has not named it, which is
     * only true of a role added straight to the database.
     */
    public static function for(?User $user, string $role): string
    {
        if ($role === 'manager' && CohortManager::is($user)) {
            return CohortManager::LABEL;
        }

        return self::of($role);
    }

    /** The academy's own name for a role, without asking who holds it. */
    public static function of(string $role): string
    {
        if (! app()->bound(self::CACHE)) {
            app()->instance(self::CACHE, Role::pluck('label', 'key')->filter()->all());
        }

        return app(self::CACHE)[$role] ?? $role;
    }
}
