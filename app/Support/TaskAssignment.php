<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may put a task on whom.
 *
 * The academy's chain of responsibility, written once: the centre manager may
 * ask anything of anyone below him, a cohort supervisor of his teachers and
 * their students, a cohort teacher of his own students. Nobody asks upward, and
 * nobody asks of a peer.
 *
 * Reach still applies on top of this. Being a supervisor permits asking a
 * teacher; being *that* teacher's supervisor is what makes it his to ask.
 */
class TaskAssignment
{
    /**
     * The roles each role may assign to, in order of seniority.
     *
     * @var array<string, array<int, string>>
     */
    private const CHAIN = [
        'manager' => ['supervisor', 'teacher', 'student'],
        'supervisor' => ['teacher', 'student'],
        'teacher' => ['student'],
    ];

    /** @return array<int, string> */
    public static function rolesAssignableBy(string $role): array
    {
        return self::CHAIN[$role] ?? [];
    }

    public static function mayAssign(string $role): bool
    {
        return self::rolesAssignableBy($role) !== [];
    }

    /**
     * Whether one person may put a task on another.
     *
     * Two questions, both of which must answer yes: does his office allow it,
     * and is this particular person within his reach?
     */
    public static function allows(Scope $scope, User $recipient): bool
    {
        $permitted = self::rolesAssignableBy($scope->role());

        if ($permitted === []) {
            return false;
        }

        $recipient->loadMissing('roles');
        $holds = $recipient->roles->pluck('role')->all();

        if (array_intersect($permitted, $holds) === []) {
            return false;
        }

        return self::withinReach($scope, $recipient, $holds);
    }

    /**
     * @param  array<int, string>  $holds
     */
    private static function withinReach(Scope $scope, User $recipient, array $holds): bool
    {
        if ($scope->reachesAll()) {
            return true;
        }

        $circles = $scope->circleIds() ?? collect();

        // A student is reachable through the cohort he sits in.
        if (in_array('student', $holds, true) && ! in_array('teacher', $holds, true)) {
            return $recipient->circle_id !== null && $circles->contains($recipient->circle_id);
        }

        // A teacher, through any cohort he teaches.
        return $recipient->circles()->whereIn('circles.id', $circles)->exists();
    }

    /**
     * Everyone this person may put a task on.
     *
     * @return Collection<int, User>
     */
    public static function candidatesFor(Scope $scope): Collection
    {
        $permitted = self::rolesAssignableBy($scope->role());

        if ($permitted === []) {
            return collect();
        }

        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('role', $permitted))
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $candidate) => self::allows($scope, $candidate))
            ->values();
    }
}
