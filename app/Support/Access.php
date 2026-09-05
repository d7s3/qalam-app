<?php

namespace App\Support;

use App\Models\Role;
use App\Models\Screen;
use App\Models\StageScreenPermission;
use App\Models\User;
use App\Models\UserScreenOverride;
use Illuminate\Support\Collection;

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

    private const CACHE_CUSTOM_ROLE = 'access_custom_role';

    private const CACHE_STAGE_IDS = 'access_stage_ids';

    private const CACHE_STAGE_PERMS = 'access_stage_permissions';

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

        $role = self::heldRole($user, $role);

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

        // Someone bound to particular programmes is answered by them: each may
        // open or close a page for a role inside itself, and holding the role in
        // two programmes means holding whatever either of them grants. Someone
        // whose reach is the whole centre has no programme to ask.
        $stageIds = self::stageIdsFor($user, $role);

        if ($stageIds !== null && $stageIds->isNotEmpty()) {
            return self::grantedInAnyStage($stageIds, $role, $screen['id']);
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
     * The role whose grants answer for a reader.
     *
     * A custom role has no guard of its own — every one of them signs in through
     * `staff`, which is an area of the application rather than a job. Asking
     * what "staff" may open answers about a role nobody holds, and the grants
     * written for the role the person actually holds are never consulted: the
     * custom role could be given pages and open none of them.
     */
    private static function heldRole(?User $user, string $role): string
    {
        if ($role !== 'staff' || ! $user?->staff_role_id) {
            return $role;
        }

        $key = self::CACHE_CUSTOM_ROLE.":{$user->staff_role_id}";

        if (! app()->bound($key)) {
            self::$cached[$key] = true;
            app()->instance($key, ['key' => Role::whereKey($user->staff_role_id)->value('key')]);
        }

        return app($key)['key'] ?? $role;
    }

    /**
     * Whether any programme the person holds this role in opens the screen.
     *
     * A programme's own word replaces the central grant rather than adding to
     * it, so a programme may close a page the role is granted everywhere else.
     * The seniority chain is still walked inside each programme: what a teacher
     * may open there, his supervisor may open there.
     *
     * Two programmes are a union, not an intersection. A page closed in one is
     * closed for that programme's work, not taken from the person wherever he
     * goes — which is what `Scope` is for, and it is asked separately.
     */
    private static function grantedInAnyStage(Collection $stageIds, string $role, int $screenId): bool
    {
        $exceptions = self::stagePermissions($stageIds);
        $chain = RoleHierarchy::chainFor($role);

        foreach ($stageIds as $stageId) {
            foreach ($chain as $carried) {
                $granted = $exceptions[$stageId][$carried][$screenId]
                    ?? in_array($screenId, RolePages::enabledScreenIdsFor($carried), true);

                if ($granted) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The programmes a person holds a role in, remembered for the request.
     *
     * The sidebar asks about two dozen pages on every render, and resolving the
     * reach afresh for each would be two dozen times the queries.
     */
    private static function stageIdsFor(?User $user, string $role): ?Collection
    {
        if (! $user) {
            return null;
        }

        $key = self::CACHE_STAGE_IDS.":{$user->id}:{$role}";

        if (! app()->bound($key)) {
            self::$cached[$key] = true;

            // Wrapped, because "no programmes — the whole centre" is null, and a
            // null cannot be told apart from an unbound key in the container.
            app()->instance($key, ['ids' => Scope::for($user, $role)->stageIds()]);
        }

        return app($key)['ids'];
    }

    /**
     * Every programme exception for a set of programmes, in one query.
     *
     * @return array<int, array<string, array<int, bool>>>
     */
    private static function stagePermissions(Collection $stageIds): array
    {
        $key = self::CACHE_STAGE_PERMS.':'.$stageIds->sort()->implode(',');

        if (! app()->bound($key)) {
            self::$cached[$key] = true;

            $table = (new StageScreenPermission)->getTable();

            $rows = StageScreenPermission::query()
                ->whereIn('stage_id', $stageIds)
                ->join('roles', 'roles.id', '=', $table.'.role_id')
                ->get([
                    $table.'.stage_id',
                    'roles.key as role_key',
                    $table.'.screen_id',
                    $table.'.is_allowed',
                ]);

            $map = [];

            foreach ($rows as $row) {
                $map[$row->stage_id][$row->role_key][$row->screen_id] = (bool) $row->is_allowed;
            }

            app()->instance($key, $map);
        }

        return app($key);
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
