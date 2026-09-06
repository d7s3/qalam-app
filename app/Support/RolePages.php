<?php

namespace App\Support;

use App\Models\RoleScreenPermission;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Whether a page is open, asked the way the sidebars have always asked it.
 *
 * The decision itself moved to `Access`, which weighs the person as well as his
 * role. This kept its own shape so that the seventy-odd call sites in the six
 * sidebars and the page middleware did not have to change with it: they still
 * name a role and a route, and now the person signed in under that role is
 * taken into account too.
 */
class RolePages
{
    private const CACHE_GRANTS = 'role_pages_grants';

    private const CACHE_ALL_SCREENS = 'role_pages_all_screens';

    /** The container keys this class has bound, so it can let go of them. */
    private static array $cached = [];

    public static function isEnabled(string $role, ?string $routeName): bool
    {
        return Access::canSee(self::signedInAs($role), $role, $routeName);
    }

    /**
     * The person signed in under a role's guard, if that role has one.
     *
     * A composed role has no guard of its own — it is a bundle of screens, not
     * an area of the application — so asking for one would throw. Such a role is
     * answered for on its own terms, with nobody in particular in hand.
     */
    private static function signedInAs(string $role): ?User
    {
        if (! config()->has("auth.guards.{$role}")) {
            return null;
        }

        return Auth::guard($role)->user();
    }

    /**
     * The dashboard (and any other screen flagged `is_protected` at seed time)
     * is where a user lands right after login — disabling it would strand
     * them with nowhere else to go, so it can never be turned off.
     */
    public static function isProtected(string $routeName): bool
    {
        return str_ends_with($routeName, '.dashboard');
    }

    /**
     * The screens a role grants, before any exception for a person.
     *
     * Memoized per request: `Access` asks once per page the sidebar draws, and
     * an uncached query here would multiply into a dozen identical ones.
     *
     * @return array<int, int>
     */
    public static function enabledScreenIdsFor(string $role): array
    {
        $key = self::CACHE_GRANTS.":{$role}";

        if (! app()->bound($key)) {
            self::$cached[$key] = true;
            app()->instance($key, RoleScreenPermission::query()
                ->whereHas('role', fn ($query) => $query->where('key', $role))
                ->pluck('screen_id')
                ->all());
        }

        return app($key);
    }

    /** Forget the loaded grants; see `Access::forget()`. */
    public static function forget(): void
    {
        foreach (array_keys(self::$cached) as $key) {
            app()->forgetInstance($key);
        }

        self::$cached = [];
    }

    /**
     * Every screen in the catalog, for the screens that manage permissions.
     *
     * @return array<string, array{id: int, route_name: string, is_protected: bool}>
     */
    public static function allScreens(): array
    {
        if (! app()->bound(self::CACHE_ALL_SCREENS)) {
            self::$cached[self::CACHE_ALL_SCREENS] = true;
            app()->instance(self::CACHE_ALL_SCREENS, Screen::query()
                ->get(['id', 'route_name', 'is_protected'])
                ->keyBy('route_name')
                ->map(fn ($screen) => [
                    'id' => $screen->id,
                    'route_name' => $screen->route_name,
                    'is_protected' => (bool) $screen->is_protected,
                ])
                ->all());
        }

        return app(self::CACHE_ALL_SCREENS);
    }
}
