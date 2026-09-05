<?php

namespace App\Support;

use App\Models\Circle;
use App\Models\Student;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * How much of the academy one person reaches.
 *
 * The reach was written out again inside every screen that needed it — a chain
 * of `if (auth()->guard('teacher')->check())` narrowing a query by the circles
 * he teaches, `elseif` supervisor by the stages he holds, and nothing at all for
 * a manager. Repeated, it drifted; and it carried a fault the page middleware
 * had already been fixed for and warns about in its own comment: a person may be
 * signed in under two guards at once — a teacher who used the "open as student"
 * link, or someone who holds two roles — and a chain that asks the guards in a
 * fixed order answers for whichever it happens to reach first, not for the page
 * he is standing on.
 *
 * So the reach is taken from the page's own role, exactly as permission is, and
 * lives here where it can be read once and tested.
 */
class Scope
{
    /**
     * The cohorts once found, since a screen asks more than once in a render
     * and the answer cannot change within one.
     *
     * @var Collection<int, int>|null
     */
    private const CACHE = 'scope_for_role';

    /** @var array<string, true> */
    private static array $cached = [];

    private ?Collection $circles = null;

    private bool $circlesResolved = false;

    private ?Collection $stages = null;

    private bool $stagesResolved = false;

    private function __construct(
        private readonly ?User $user,
        private readonly string $role,
    ) {}

    /**
     * The reach of the person signed in for a page.
     *
     * The role comes from the route's name — `supervisor.exceeded-limits` is a
     * supervisor's page however many other roles its reader also holds.
     */
    public static function forRoute(?string $routeName = null): self
    {
        return self::forRole(self::resolveRole($routeName));
    }

    /**
     * The reach of whoever is signed in under a named role.
     */
    public static function forRole(string $role): self
    {
        // Kept for the rest of the request. A screen asks for its reach in
        // several places while rendering once, and a fresh instance each time
        // means the cohorts are looked up again each time — which is how a page
        // that should cost one query came to cost several.
        $key = self::CACHE.":{$role}";

        if (! app()->bound($key)) {
            $user = config()->has("auth.guards.{$role}")
                ? Auth::guard($role)->user()
                : null;

            self::$cached[$key] = true;
            app()->instance($key, new self($user, $role));
        }

        return app($key);
    }

    /**
     * Forget the reaches read, for when who is signed in changes mid-request —
     * which happens in tests, and when a magic link signs someone in.
     */
    public static function forget(): void
    {
        foreach (array_keys(self::$cached) as $key) {
            app()->forgetInstance($key);
        }

        self::$cached = [];
    }

    /**
     * Which role a request is being made in.
     *
     * Normally the page says so: `supervisor.exceeded-limits` is a supervisor's
     * page. But a Livewire update is its own route and names no role, and so is
     * a console command — and there the guards must be asked after all.
     *
     * They are asked from the narrowest reach to the widest, so that a person
     * signed in under two at once is answered for by the smaller. An ambiguity
     * about how much someone may see should resolve towards showing less; the
     * cost is a report that looks short, and the alternative is one that shows
     * what it should not.
     */
    public static function resolveRole(?string $routeName = null): string
    {
        $role = Str::before($routeName ?? request()->route()?->getName() ?? '', '.');

        if (config()->has("auth.guards.{$role}")) {
            return $role;
        }

        foreach (['student', 'guardian', 'teacher', 'supervisor', 'staff', 'manager'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return '';
    }

    public static function for(?User $user, string $role): self
    {
        return new self($user, $role);
    }

    /**
     * Whether this person sees the whole academy.
     *
     * The manager does by his office; a super administrator does by his mark,
     * whichever page he is standing on.
     */
    public function reachesAll(): bool
    {
        if ($this->user?->is_super_admin) {
            return true;
        }

        // A reach set on the holding overrules the role's own: this is what
        // lets a manager's role be held over two programmes rather than all,
        // which is a programme director and needs no code of its own.
        $assigned = $this->assignedScope();

        if ($assigned) {
            return $assigned->scope_type === UserRole::SCOPE_ALL;
        }

        // The manager's office reaches the centre. A custom role does not: it
        // is created empty and given what it needs, and 'staff' is the guard
        // every custom role rides — so answering true here handed each new role
        // every student in the academy before anyone had chosen to.
        return $this->role === 'manager';
    }

    /**
     * The reach written on this person's holding of this role, if any.
     */
    private function assignedScope(): ?UserRole
    {
        if (! $this->user) {
            return null;
        }

        $holding = $this->user->roles->firstWhere('role', $this->role);

        return $holding?->scope_type ? $holding : null;
    }

    /**
     * The circles within reach, or null when that is all of them.
     *
     * @return Collection<int, int>|null
     */
    public function circleIds(): ?Collection
    {
        if ($this->circlesResolved) {
            return $this->circles;
        }

        $this->circlesResolved = true;

        return $this->circles = $this->resolveCircleIds();
    }

    /**
     * The programmes within reach, or null when that is all of them.
     *
     * Derived from the cohorts rather than asked for on its own, so "which
     * programmes am I in" and "which cohorts am I in" cannot drift apart.
     *
     * The supervisor is the exception, and deliberately: he holds programmes
     * directly, and a programme he holds before any cohort exists in it is
     * still a programme he holds.
     */
    public function stageIds(): ?Collection
    {
        if ($this->stagesResolved) {
            return $this->stages;
        }

        $this->stagesResolved = true;

        return $this->stages = $this->resolveStageIds();
    }

    /** @return Collection<int, int>|null */
    private function resolveStageIds(): ?Collection
    {
        if ($this->reachesAll() || ! $this->user) {
            return null;
        }

        $assigned = $this->assignedScope();

        if ($assigned && $assigned->scope_type === UserRole::SCOPE_STAGES) {
            return collect($assigned->scope_ids ?? [])->map(fn ($id) => (int) $id)->values();
        }

        if (! $assigned && $this->role === 'supervisor') {
            return $this->user->stages()->pluck('stages.id');
        }

        $circles = $this->circleIds();

        if ($circles === null) {
            return null;
        }

        return Circle::whereIn('id', $circles)->pluck('stage_id')->filter()->unique()->values();
    }

    /**
     * The cohorts themselves, in one query.
     *
     * Asked for as models rather than as ids and then as models, because that
     * was two round trips where the code this replaced made one — a reach
     * computed in one place must not cost more than the reach written into each
     * screen did.
     *
     * @return Builder<Circle>
     */
    public function circleQuery()
    {
        $query = Circle::query();

        if ($this->reachesAll() || ! $this->user) {
            return $query;
        }

        if ($assigned = $this->assignedScope()) {
            $ids = collect($assigned->scope_ids ?? [])->map(fn ($id) => (int) $id);

            return $assigned->scope_type === UserRole::SCOPE_STAGES
                ? $query->whereIn('stage_id', $ids)
                : $query->whereIn('id', $ids);
        }

        $me = $this->user->id;

        return match ($this->role) {
            'teacher' => $query->whereHas('teachers', fn ($q) => $q->where('users.id', $me)),
            'supervisor' => $query->whereHas('stage.supervisors', fn ($q) => $q->where('users.id', $me)),
            'student' => $query->whereKey($this->user->circle_id ?? 0),
            'guardian' => $query->whereIn('id', User::where('guardian_id', $me)->pluck('circle_id')->filter()),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** @return Collection<int, int>|null */
    private function resolveCircleIds(): ?Collection
    {
        if ($this->reachesAll() || ! $this->user) {
            return null;
        }

        if ($assigned = $this->assignedScope()) {
            $ids = collect($assigned->scope_ids ?? [])->map(fn ($id) => (int) $id);

            return $assigned->scope_type === UserRole::SCOPE_STAGES
                ? Circle::whereIn('stage_id', $ids)->pluck('id')
                : $ids;
        }

        return match ($this->role) {
            // A teacher reaches the cohorts he teaches.
            'teacher' => $this->user->circles()->pluck('circles.id'),

            // A cohort supervisor reaches every cohort of the programmes he holds.
            'supervisor' => Circle::whereIn('stage_id', $this->user->stages()->pluck('stages.id'))->pluck('id'),

            // A student reaches his own cohort; a guardian his children's.
            'student' => collect(array_filter([$this->user->circle_id])),
            'guardian' => $this->user->students()->pluck('circle_id')->filter()->unique()->values(),

            default => collect(),
        };
    }

    /**
     * Narrow a query over students to what this person may see.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function applyToStudents(Builder $query): Builder
    {
        if ($this->reachesAll()) {
            return $query;
        }

        // A reach written on the holding speaks for cohorts, so it answers for
        // these two before their roles' own narrowing does.
        if ($this->assignedScope()) {
            return $query->whereIn('circle_id', $this->circleIds() ?? collect());
        }

        // His own record, and nobody else's.
        if ($this->role === 'student') {
            return $query->where('id', $this->user?->id ?? 0);
        }

        if ($this->role === 'guardian') {
            return $query->where('guardian_id', $this->user?->id ?? 0);
        }

        return $query->whereIn('circle_id', $this->circleIds() ?? collect());
    }

    /**
     * Narrow a query over circles to what this person may see.
     *
     * @param  Builder<Circle>  $query
     * @return Builder<Circle>
     */
    public function applyToCircles(Builder $query): Builder
    {
        if ($this->reachesAll()) {
            return $query;
        }

        // Narrowed by the same conditions `circleQuery()` uses, so asking for
        // the cohorts costs one query rather than a list of ids and then a
        // fetch by that list.
        return $query->whereIn('id', $this->circleQuery()->select('circles.id'));
    }

    /**
     * The people whose tasks this reader may follow.
     *
     * A cohort teacher follows his own; a cohort supervisor follows the teachers
     * of his programmes and himself; a centre manager follows everyone. The
     * chain is the same one that governs students, read a rung higher.
     *
     * Null means everyone, as it does for cohorts.
     *
     * @return Collection<int, int>|null
     */
    public function taskAssigneeIds(): ?Collection
    {
        if ($this->reachesAll() || ! $this->user) {
            return null;
        }

        $mine = collect([$this->user->id]);

        return match ($this->role) {
            // Those who teach the cohorts within reach, and himself.
            'supervisor' => User::whereHas(
                'circles',
                fn ($q) => $q->whereIn('circles.id', $this->circleIds() ?? collect()),
            )->pluck('id')->merge($mine)->unique()->values(),

            default => $mine,
        };
    }

    /**
     * Narrow a query over tasks to those this reader may follow.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function applyToTasks(Builder $query): Builder
    {
        $ids = $this->taskAssigneeIds();

        if ($ids === null) {
            return $query;
        }

        // A task he was given, or one he gave — a person follows what he asked
        // of others as well as what was asked of him.
        return $query->where(
            fn ($q) => $q->whereIn('assigned_to_id', $ids)
                ->orWhere(fn ($sub) => $sub->where('created_by_id', $this->user?->id ?? 0)),
        );
    }

    public function role(): string
    {
        return $this->role;
    }

    public function user(): ?User
    {
        return $this->user;
    }
}
