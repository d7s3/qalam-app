<?php

namespace App\Services;

use App\Models\Motivation;
use App\Models\PortalMessage;
use App\Models\PortalMessageRead;
use App\Models\User;
use App\Support\RoleHierarchy;
use Illuminate\Support\Collection;

/**
 * What meets a person when he opens the application.
 *
 * Two things, and they are different in kind. One is a word somebody addressed
 * to him and has not yet read; the other is something worth meeting whoever he
 * is — a verse, a hadith, a word of the salaf.
 */
class PortalService
{
    /**
     * The offices a role may address.
     *
     * His own — a supervisor speaks to supervisors — and every office his
     * carries. Never upward: an announcement is not how a teacher reaches the
     * manager, and the application already has a conversation for that.
     *
     * @return array<int, string>
     */
    public static function canAddress(string $roleKey): array
    {
        return array_values(array_unique(array_merge([$roleKey], RoleHierarchy::inheritedBy($roleKey))));
    }

    public static function mayAddress(string $fromRole, string $toRole): bool
    {
        return in_array($toRole, self::canAddress($fromRole), true);
    }

    /**
     * Announce a word, refusing anything addressed upward.
     *
     * @param  array<int, string>  $roles
     * @param  array<int, int>  $userIds
     */
    public static function announce(
        User $sender,
        string $senderRole,
        string $body,
        array $roles = [],
        array $userIds = [],
        ?string $title = null,
        bool $showSender = true,
        ?string $startsOn = null,
        ?string $endsOn = null,
    ): ?PortalMessage {
        $allowed = array_values(array_filter($roles, fn (string $role) => self::mayAddress($senderRole, $role)));

        if ($allowed === [] && $userIds === []) {
            return null;
        }

        $message = PortalMessage::create([
            'sender_id' => $sender->id,
            'sender_role' => $senderRole,
            'title' => $title,
            'body' => $body,
            'show_sender' => $showSender,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);

        foreach ($allowed as $role) {
            $message->audiences()->create(['role_key' => $role]);
        }

        foreach ($userIds as $id) {
            $message->audiences()->create(['user_id' => $id]);
        }

        return $message;
    }

    /**
     * The announcements waiting for a person, oldest first.
     *
     * Addressed to the office he is reading under, or to him by name. A message
     * he has read does not come back.
     *
     * @return Collection<int, PortalMessage>
     */
    public static function waitingFor(User $user, string $roleKey): Collection
    {
        $read = PortalMessageRead::where('user_id', $user->id)->pluck('portal_message_id');

        return PortalMessage::query()
            ->live()
            ->whereKeyNot($read)
            ->whereHas('audiences', fn ($q) => $q
                ->where('role_key', $roleKey)
                ->orWhere('user_id', $user->id))
            // A person is not announced to by himself.
            ->where('sender_id', '!=', $user->id)
            ->with('sender')
            ->orderBy('created_at')
            ->get();
    }

    public static function markRead(PortalMessage $message, User $user): void
    {
        PortalMessageRead::firstOrCreate(
            ['portal_message_id' => $message->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    /**
     * Something to meet on opening, or nothing.
     *
     * Only what somebody has approved is ever drawn, and a hadith is drawn only
     * under a grading the academy accepts — the review is the guard, and this
     * is the second lock on the same door.
     */
    public static function motivationFor(?User $user = null): ?Motivation
    {
        $drawn = Motivation::showable()
            ->inRandomOrder()
            ->get()
            ->first(fn (Motivation $one) => $one->gradeIsAcceptable());

        $drawn?->increment('shown_count');

        return $drawn;
    }
}
