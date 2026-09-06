<?php

namespace App\Support;

use App\Models\AcademicCalendarEvent;
use App\Models\CalendarEventGrant;
use App\Models\Role;
use App\Models\User;
use App\Services\MessagingService;

/**
 * Who may see an event, and who may hand that sight on.
 *
 * Sight travels down the offices one step at a time and never widens: an office
 * grants only what it holds, and only to an office beneath it. What makes this
 * hold without any cleanup is that a grant is read as valid only while the
 * office that made it can still see the event — so withdrawing sight at the top
 * withdraws it all the way down, with nothing deleted and nothing missed.
 *
 * The owner of an event is the office that created it, and it is where every
 * chain begins.
 */
class CalendarVisibility
{
    /** Guard against a grant chain that somehow points at itself. */
    private const MAX_DEPTH = 12;

    /**
     * Whether an office may see an event.
     *
     * The super administrator is above this as he is above the rest, and the
     * office that created it never loses sight of its own.
     */
    public static function canSee(AcademicCalendarEvent $event, string $roleKey, ?User $user = null): bool
    {
        if ($user?->is_super_admin) {
            return true;
        }

        if (self::ownerRoleKey($event) === $roleKey) {
            return true;
        }

        return self::chainHolds($event, $roleKey, 0);
    }

    /** Whether anyone has started handing this event down at all. */
    public static function governs(AcademicCalendarEvent $event): bool
    {
        return CalendarEventGrant::where('academic_calendar_event_id', $event->id)->exists();
    }

    /**
     * Whether an office sees an event on a screen.
     *
     * An event nobody has handed down is not governed by the chain yet and
     * keeps the behaviour it always had — turning this on must not empty a
     * calendar already full of events that predate it. The first grant takes
     * the event over, and from then on the chain is the whole answer.
     */
    public static function visibleTo(AcademicCalendarEvent $event, string $roleKey, ?User $user = null): bool
    {
        if (! self::governs($event)) {
            return true;
        }

        return self::canSee($event, $roleKey, $user);
    }

    /**
     * Which offices this one may hand sight on to.
     *
     * Those it carries by seniority, and no others — an office cannot hand
     * anything to one above it or beside it.
     *
     * @return array<int, string>
     */
    public static function canGrantTo(string $roleKey): array
    {
        return array_values(array_diff(RoleHierarchy::inheritedBy($roleKey), [$roleKey]));
    }

    /**
     * Hand sight of an event to an office beneath.
     *
     * Refused when the giver cannot see it himself, which is the whole rule:
     * ten may become five and never eleven.
     */
    public static function grant(AcademicCalendarEvent $event, string $fromRoleKey, string $toRoleKey, ?User $by = null): bool
    {
        if (! self::canSee($event, $fromRoleKey, $by)) {
            return false;
        }

        if (! in_array($toRoleKey, self::canGrantTo($fromRoleKey), true)) {
            return false;
        }

        $to = Role::where('key', $toRoleKey)->first();
        $from = Role::where('key', $fromRoleKey)->first();

        if (! $to) {
            return false;
        }

        CalendarEventGrant::updateOrCreate(
            ['academic_calendar_event_id' => $event->id, 'role_id' => $to->id],
            ['granted_by_role_id' => $from?->id, 'granted_by_id' => $by?->id],
        );

        return true;
    }

    /** Take back a grant this office made. */
    public static function revoke(AcademicCalendarEvent $event, string $fromRoleKey, string $toRoleKey): void
    {
        $to = Role::where('key', $toRoleKey)->first();

        if (! $to) {
            return;
        }

        CalendarEventGrant::where('academic_calendar_event_id', $event->id)
            ->where('role_id', $to->id)
            ->whereHas('grantedByRole', fn ($q) => $q->where('key', $fromRoleKey))
            ->delete();
    }

    /**
     * Every office that can see an event today.
     *
     * @return array<int, string>
     */
    public static function rolesSeeing(AcademicCalendarEvent $event): array
    {
        return Role::pluck('key')
            ->filter(fn (string $key) => self::canSee($event, $key))
            ->values()
            ->all();
    }

    /**
     * The office that created the event, if it can be told.
     *
     * Events made before anyone was recorded as making them have no owner, and
     * an ownerless event is governed by its grants alone.
     */
    public static function ownerRoleKey(AcademicCalendarEvent $event): ?string
    {
        $type = $event->created_by_type;

        if (! $type) {
            return null;
        }

        foreach (MessagingService::MODELS as $key => $model) {
            if ($model === $type) {
                return $key;
            }
        }

        return null;
    }

    /** Walk a grant back to the owner, refusing a chain broken anywhere along it. */
    private static function chainHolds(AcademicCalendarEvent $event, string $roleKey, int $depth): bool
    {
        if ($depth > self::MAX_DEPTH) {
            return false;
        }

        $role = Role::where('key', $roleKey)->first();

        if (! $role) {
            return false;
        }

        $grant = CalendarEventGrant::where('academic_calendar_event_id', $event->id)
            ->where('role_id', $role->id)
            ->first();

        if (! $grant) {
            return false;
        }

        // Granted by the owner directly: the chain is one link and it holds.
        if ($grant->granted_by_role_id === null) {
            return true;
        }

        $granter = Role::find($grant->granted_by_role_id);

        if (! $granter) {
            return false;
        }

        if (self::ownerRoleKey($event) === $granter->key) {
            return true;
        }

        return self::chainHolds($event, $granter->key, $depth + 1);
    }
}
