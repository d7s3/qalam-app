<?php

namespace App\Support;

use App\Models\Screen;
use App\Models\User;
use App\Models\UserScreenOverride;

/**
 * The one answer to "may this person open this page?".
 *
 * Three things had been answering it separately — the guard on the route, the
 * role's granted screens, and a teacher's own action permissions — and none of
 * them knew about the others. When a page did not appear, the reason could be
 * in any of the three and no screen showed all three at once.
 *
 * This settles the page question in one place, in a fixed order:
 *
 *   1. a super administrator sees everything, always;
 *   2. an exception written for this person wins, whichever way it points;
 *   3. otherwise his role decides, as it always did.
 *
 * The guard is still the outer wall — it decides which area of the application
 * a person may enter at all — and it is not touched here.
 */
class Access
{
    /** Where the loaded exceptions and screens live for the rest of the request. */
    private const CACHE_OVERRIDES = 'access_user_overrides';

    private const CACHE_SCREENS = 'access_screens';

    /** The container keys this class has bound, so it can let go of them. */
    private static array $cached = [];

    /**
     * Whether a page is open to a person.
     *
     * A route with no registered screen is open: pages are added to the code
     * before anyone thinks to restrict them, and the safe reading of "not yet
     * described" is "not yet restricted" — the same reading the role check has
     * always taken.
     */
    public static function canSee(?User $user, string $role, ?string $routeName): bool
    {
        if (! $routeName) {
            return true;
        }

        if ($user?->is_super_admin) {
            return true;
        }

        $screen = self::screenFor($routeName);

        if (! $screen) {
            return true;
        }

        if ($screen['is_protected']) {
            return true;
        }

        if ($user) {
            $override = self::overridesFor($user->id)[$screen['id']] ?? null;

            if ($override !== null) {
                return $override;
            }
        }

        // A role holds its own grants and those of every role it carries: what
        // a teacher may open, his supervisor may open, and the manager above
        // them both — without the same grant being made three times and then
        // drifting apart.
        foreach (RoleHierarchy::chainFor($role) as $carried) {
            if (in_array($screen['id'], RolePages::enabledScreenIdsFor($carried), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The exceptions written for one person, keyed by screen id.
     *
     * Memoized per request: the sidebar asks about a dozen pages on every
     * render, and each ask would otherwise be its own query.
     *
     * @return array<int, bool>
     */
    public static function overridesFor(int $userId): array
    {
        $key = self::CACHE_OVERRIDES.":{$userId}";

        if (! app()->bound($key)) {
            self::$cached[$key] = true;
            app()->instance($key, UserScreenOverride::where('user_id', $userId)
                ->pluck('is_allowed', 'screen_id')
                ->map(fn ($allowed) => (bool) $allowed)
                ->all());
        }

        return app($key);
    }

    /**
     * Forget what was read, so the next question reads it again.
     *
     * Called after any change to a grant or an exception: the screen that hands
     * out access reads the table back immediately to redraw it, and without this
     * it would redraw the state from before the change.
     */
    public static function forget(): void
    {
        foreach (array_keys(self::$cached) as $key) {
            app()->forgetInstance($key);
        }

        self::$cached = [];

        RolePages::forget();
        RoleHierarchy::forget();
    }

    /**
     * The screen that speaks for a route: its own, or the nearest parent whose
     * name the route extends — `supervisor.odes.paths` is covered by
     * `supervisor.odes` when it has no entry of its own.
     *
     * @return array{id: int, route_name: string, is_protected: bool}|null
     */
    private static function screenFor(string $routeName): ?array
    {
        return collect(self::screens())
            ->filter(fn ($screen) => $routeName === $screen['route_name']
                || str_starts_with($routeName, $screen['route_name'].'.'))
            ->sortByDesc(fn ($screen) => strlen($screen['route_name']))
            ->first();
    }

    /**
     * @return array<string, array{id: int, route_name: string, is_protected: bool}>
     */
    private static function screens(): array
    {
        if (! app()->bound(self::CACHE_SCREENS)) {
            self::$cached[self::CACHE_SCREENS] = true;
            app()->instance(self::CACHE_SCREENS, Screen::query()
                ->get(['id', 'route_name', 'is_protected'])
                ->keyBy('route_name')
                ->map(fn ($screen) => [
                    'id' => $screen->id,
                    'route_name' => $screen->route_name,
                    'is_protected' => (bool) $screen->is_protected,
                ])
                ->all());
        }

        return app(self::CACHE_SCREENS);
    }
}
