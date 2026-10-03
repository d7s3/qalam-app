<?php

namespace App\Services;

use App\Models\Guardian;
use App\Rules\SaudiPhone;
use Illuminate\Support\Str;

/**
 * The guardian a student names for himself.
 *
 * A student who has no guardian on record is asked for one — his name and
 * phone — and the account is made there and then. Brothers name the same
 * phone, so a guardian already on record by that phone is the one returned.
 *
 * A new account is opened by the magic link the academy sends the family,
 * not by a password: it is given one nobody knows. It used to be given the
 * phone number itself, under an address made of the same number, so anyone
 * who knew a parent's phone could sign in as that parent and read his
 * children's records.
 */
class GuardianLinkService
{
    public function guardianFor(string $name, string $phone): Guardian
    {
        $phone = SaudiPhone::format($phone);

        $existing = Guardian::where('phone', $phone)->first();

        if ($existing) {
            return $existing;
        }

        $email = $phone.'@parent.com';

        while (Guardian::where('email', $email)->exists()) {
            $email = $phone.random_int(10, 99).'@parent.com';
        }

        return Guardian::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => Str::password(32),
            'is_approved' => true,
        ]);
    }
}
