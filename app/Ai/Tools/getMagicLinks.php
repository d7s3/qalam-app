<?php

namespace App\Ai\Tools;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Magic login links, for the manager to hand out.
 *
 * A magic link signs its holder straight into that account with no password, so
 * every row here is a credential rather than a fact about someone. The
 * assistant runs only on the manager's page, behind the manager guard, and the
 * manager can already copy these same links from the students page — this saves
 * the walk, it does not widen anyone's access.
 *
 * Accounts that never had a token get one issued, exactly as the copy button on
 * the students page does, because a link cannot be handed out otherwise.
 */
class getMagicLinks implements Tool
{
    private const MAX_LIMIT = 300;

    /**
     * The route that signs each kind of account in.
     */
    private const ROUTES = [
        'student' => 'magic-link',
        'teacher' => 'teacher.magic-link',
        'supervisor' => 'supervisor.magic-link',
        'guardian' => 'guardian.magic-link',
    ];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the magic login links (الروابط السحرية) of students, teachers, supervisors or guardians, so the manager can hand them out. '
            .'Filter with "role", "search" (part of a name or email), "circle", "stage", or "only_without_link" to find who has never been issued one. '
            .'Each link signs its holder into that account without a password, so treat every one as a credential: quote them only when the manager '
            .'has asked for links, never volunteer them alongside an unrelated answer, and never include them in a summary. '
            .'Accounts with no token yet are issued one when they appear here.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $role = (string) (($request['role'] ?? null) ?: 'student');

        if (! array_key_exists($role, self::ROUTES)) {
            return json_encode([
                'error' => 'Unsupported role.',
                'supported' => array_keys(self::ROUTES),
            ], JSON_UNESCAPED_UNICODE);
        }

        $limit = min(max((int) (($request['limit'] ?? null) ?: 100), 1), self::MAX_LIMIT);

        $query = match ($role) {
            'teacher' => Teacher::query(),
            'supervisor' => Supervisor::query(),
            'guardian' => Guardian::query(),
            default => Student::with('circle.stage'),
        };

        $this->applyFilters($query, $request, $role);

        if ($request['only_without_link'] ?? null) {
            $query->where(fn ($q) => $q->whereNull('access_token')->orWhere('access_token', ''));
        }

        $people = $query->orderBy('name')->limit($limit)->get();

        $rows = $people->map(fn ($person) => [
            'name' => $person->name,
            'circle' => $role === 'student' ? $person->circle?->name : null,
            'had_no_link_before' => blank($person->access_token),
            'magic_link' => route(self::ROUTES[$role], ['token' => $this->ensureToken($person)]),
        ])->all();

        return json_encode([
            'role' => $role,
            'returned' => count($rows),
            'warning' => 'Each link signs its holder in without a password. Share only what the manager asked for.',
            'note' => count($rows) === $limit
                ? 'The result was capped at the limit; narrow the filters or raise "limit" to see more.'
                : null,
            'people' => $rows,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyFilters(Builder $query, Request $request, string $role): void
    {
        if ($search = ($request['search'] ?? null)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $circle = $request['circle'] ?? null;
        $stage = $request['stage'] ?? null;

        if ($circle) {
            $role === 'teacher'
                ? $query->whereHas('circles', fn ($q) => $q->where('name', 'like', '%'.$circle.'%'))
                : $query->whereHas('circle', fn ($q) => $q->where('name', 'like', '%'.$circle.'%'));
        }

        if ($stage) {
            match ($role) {
                'teacher' => $query->whereHas('circles.stage', fn ($q) => $q->where('name', 'like', '%'.$stage.'%')),
                'supervisor' => $query->whereHas('stages', fn ($q) => $q->where('name', 'like', '%'.$stage.'%')),
                'guardian' => $query->whereHas('students.circle.stage', fn ($q) => $q->where('name', 'like', '%'.$stage.'%')),
                default => $query->where(function ($q) use ($stage) {
                    $q->whereHas('circle.stage', fn ($s) => $s->where('name', 'like', '%'.$stage.'%'))
                        ->orWhereHas('stage', fn ($s) => $s->where('name', 'like', '%'.$stage.'%'));
                }),
            };
        }
    }

    /**
     * A link needs a token; accounts that never had one get it now.
     */
    private function ensureToken($person): string
    {
        if (blank($person->access_token)) {
            $person->access_token = Str::random(32);
            $person->save();
        }

        return $person->access_token;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'role' => $schema->string()->enum(['student', 'teacher', 'supervisor', 'guardian'])->required(),
            'search' => $schema->string()->description('Part of a name or email to search for.'),
            'circle' => $schema->string()->description('Circle name (حلقة). Students and teachers.'),
            'stage' => $schema->string()->description('Stage name (برنامج).'),
            'only_without_link' => $schema->boolean()->description('Only accounts that have never been issued a link.'),
            'limit' => $schema->integer()->description('Maximum people to return. Defaults to 100, maximum 300.'),
        ];
    }
}
