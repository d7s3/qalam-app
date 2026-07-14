<?php

namespace App\Support;

use App\Models\RoleScreenPermission;
use App\Models\Screen;

class RolePages
{
    public static function isEnabled(string $role, ?string $routeName): bool
    {
        if (! $routeName) {
            return true;
        }

        $match = self::longestMatch($routeName);

        if (! $match) {
            return true;
        }

        if ($match['is_protected']) {
            return true;
        }

        return in_array($match['id'], self::enabledScreenIds($role), true);
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
     * @return array{id: int, route_name: string, is_protected: bool}|null
     */
    protected static function longestMatch(string $routeName): ?array
    {
        return collect(self::allScreens())
            ->filter(fn ($screen) => $routeName === $screen['route_name'] || str_starts_with($routeName, $screen['route_name'].'.'))
            ->sortByDesc(fn ($screen) => strlen($screen['route_name']))
            ->first();
    }

    /**
     * The screen catalog is global (any role can be granted any screen), so
     * it's memoized once per request regardless of which role is being
     * checked — unlike the per-role enabled set below.
     *
     * @return array<string, array{id: int, route_name: string, is_protected: bool}>
     */
    protected static function allScreens(): array
    {
        return once(fn () => Screen::query()
            ->get(['id', 'route_name', 'is_protected'])
            ->keyBy('route_name')
            ->map(fn ($screen) => [
                'id' => $screen->id,
                'route_name' => $screen->route_name,
                'is_protected' => $screen->is_protected,
            ])
            ->all());
    }

    /**
     * Memoized per request: `isEnabled()` is called once per middleware pass
     * plus once per sidebar item (a dozen-plus times per page render), so
     * an uncached query here would multiply into a dozen-plus identical ones.
     */
    protected static function enabledScreenIds(string $role): array
    {
        return once(fn () => RoleScreenPermission::query()
            ->whereHas('role', fn ($query) => $query->where('key', $role))
            ->pluck('screen_id')
            ->all());
    }
}
