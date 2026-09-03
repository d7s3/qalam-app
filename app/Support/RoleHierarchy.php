<?php

namespace App\Support;

use App\Models\Role;
use App\Models\Setting;

/**
 * Which offices carry the ones beneath them.
 *
 * The academy's rule is that seniority includes: whatever a cohort teacher may
 * do, his supervisor may do; whatever a supervisor may do, the centre manager
 * may do. So a screen granted to teachers is a screen supervisors and managers
 * hold too, without anyone granting it three times over and without the three
 * grants drifting apart afterwards.
 *
 * Kept as a setting rather than in code, so an administrator can change who
 * carries whom without a release — which is the point of the whole thing.
 */
class RoleHierarchy
{
    private const SETTING = 'role_inherits';

    private const CACHE = 'role_hierarchy_map';

    /**
     * The academy's own chain, used until an administrator says otherwise.
     *
     * @var array<string, array<int, string>>
     */
    private const DEFAULT = [
        'supervisor' => ['teacher'],
        'manager' => ['supervisor'],
    ];

    /**
     * Who each role carries directly.
     *
     * @return array<string, array<int, string>>
     */
    public static function map(): array
    {
        if (! app()->bound(self::CACHE)) {
            $stored = Setting::getVal(self::SETTING);

            if (is_string($stored)) {
                $stored = json_decode($stored, true);
            }

            app()->instance(self::CACHE, is_array($stored) ? $stored : self::DEFAULT);
        }

        return app(self::CACHE);
    }

    /**
     * Every role a role carries, directly or through another.
     *
     * A manager carries the supervisor, and through him the teacher, without
     * anyone writing that down twice.
     *
     * @return array<int, string>
     */
    public static function inheritedBy(string $role): array
    {
        $seen = [];
        $queue = self::map()[$role] ?? [];

        while ($queue !== []) {
            $next = array_shift($queue);

            // A cycle written by mistake must not spin here for ever.
            if (in_array($next, $seen, true) || $next === $role) {
                continue;
            }

            $seen[] = $next;
            $queue = array_merge($queue, self::map()[$next] ?? []);
        }

        return $seen;
    }

    /**
     * A role and everything it carries, for asking about grants.
     *
     * @return array<int, string>
     */
    public static function chainFor(string $role): array
    {
        return array_merge([$role], self::inheritedBy($role));
    }

    /**
     * The roles that carry this one — those whose holders reach its screens.
     *
     * @return array<int, string>
     */
    public static function inheritors(string $role): array
    {
        $out = [];

        foreach (array_keys(self::map()) as $candidate) {
            if (in_array($role, self::inheritedBy($candidate), true)) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    /**
     * Write a new chain, and let go of what was read.
     *
     * @param  array<string, array<int, string>>  $map
     */
    public static function set(array $map): void
    {
        Setting::setVal(self::SETTING, json_encode($map, JSON_UNESCAPED_UNICODE));

        self::forget();
        Access::forget();
    }

    public static function forget(): void
    {
        app()->forgetInstance(self::CACHE);
    }

    /**
     * The roles an administrator may arrange, newest custom ones included.
     *
     * @return array<string, string> keyed by key, valued by label
     */
    public static function arrangeable(): array
    {
        return Role::orderByDesc('is_system')->orderBy('id')->pluck('label', 'key')->all();
    }
}
