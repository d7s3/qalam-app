<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The code an account is opened with, when somebody else opened it.
 *
 * Most people here cannot register themselves — a supervisor, a manager, a
 * teacher of a cohort are made for them by the office above. Such an account has
 * to be reachable somehow, and the academy's own way is the plainest: one code
 * everybody knows, said over the phone, changed on the way in.
 *
 * One code for every new account is only safe while it cannot survive the first
 * sign-in, which is what `users.must_change_password` is for: he arrives, and
 * the application will show him nothing until he has chosen his own.
 *
 * It is a setting rather than a constant because the academy may want a code
 * of its own, and because the day it decides one shared code is too loose, the
 * change is a value in a field rather than a release.
 */
class StartingPassword
{
    private const SETTING = 'starting_password';

    /** What the academy hands out until it says otherwise. */
    public const DEFAULT = '123456';

    /** The code as it stands, never blank. */
    public static function code(): string
    {
        $stored = Setting::getVal(self::SETTING);

        return is_string($stored) && trim($stored) !== '' ? trim($stored) : self::DEFAULT;
    }

    public static function set(string $code): void
    {
        Setting::setVal(self::SETTING, trim($code));
    }

    /** Whether the academy is still handing out the one it was given. */
    public static function isDefault(): bool
    {
        return self::code() === self::DEFAULT;
    }
}
