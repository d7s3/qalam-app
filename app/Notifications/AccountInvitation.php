<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\RoleTitle;
use App\Support\StartingPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The letter that opens an account somebody else made.
 *
 * An account created by an administrator has a password nobody knows — a long
 * random string, made so that no person ever handles another person's password.
 * That leaves the man with no way in, and telling him «go and use forgot
 * password» is an instruction the academy has to remember to give, in a channel
 * it has to find, for every account it ever makes.
 *
 * So the account announces itself. It carries the academy's starting code —
 * which is safe to write down only because it cannot survive the first sign-in
 * — and, for whoever would rather not use a code everyone knows, a signed link
 * that sets a password straight away and stops working on its own.
 *
 * Three days, not the hour a password reset lasts: a reset is asked for by
 * somebody sitting at the screen, and an invitation arrives at night and is
 * opened on Sunday.
 */
class AccountInvitation extends Notification
{
    /** How long the invitation stands. */
    public const DAYS = 3;

    public function __construct(
        private readonly string $inviterName,
        private readonly string $role = 'manager',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('حسابك في :name', ['name' => config('brand.name')]))
            ->view('mail.invitation', [
                'url' => self::linkFor($notifiable),
                'loginUrl' => route('login'),
                'email' => $notifiable->email,
                'code' => StartingPassword::code(),
                'recipientName' => $notifiable->name,
                'inviterName' => $this->inviterName,
                'roleLabel' => RoleTitle::for($notifiable instanceof User ? $notifiable : null, $this->role),
                'days' => self::DAYS,
            ]);
    }

    /**
     * The link itself, signed and dated.
     *
     * Made here rather than in the screen that sends it, so the letter and the
     * button that shows the administrator what was sent cannot drift apart.
     */
    public static function linkFor(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'invitation.accept',
            now()->addDays(self::DAYS),
            ['user' => $notifiable->getKey()],
        );
    }
}
