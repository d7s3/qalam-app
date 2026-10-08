<?php

namespace App\Console\Commands;

use App\Models\Guardian;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Close the guardian accounts that anybody could open.
 *
 * Until October 2026 the account a student made for his guardian took the
 * guardian's phone as its password, under an address made of the same
 * number. The screens no longer do so, but the accounts already made still
 * open that way. Each one is given a password nobody knows; families keep
 * signing in by the magic link the academy sends them, which this leaves
 * untouched. Run once after deploying; running it again changes nothing.
 */
class SecureGuardianPasswords extends Command
{
    protected $signature = 'guardians:secure-passwords {--dry-run : عرض العدد فقط دون تغيير}';

    protected $description = 'يغيّر كلمة مرور كل ولي أمر كانت كلمة مروره رقم جواله، ويبقى دخوله بالرابط كما هو';

    public function handle(): int
    {
        $exposed = Guardian::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->filter(fn (Guardian $guardian) => $this->opensWithItsPhone($guardian));

        if ($exposed->isEmpty()) {
            $this->info('لا يوجد ولي أمر كلمة مروره رقم جواله.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("{$exposed->count()} ولي أمر كلمة مروره رقم جواله. لم يُغيَّر شيء.");

            return self::SUCCESS;
        }

        foreach ($exposed as $guardian) {
            $guardian->update(['password' => Str::password(32)]);
        }

        $this->info("أُمِّن {$exposed->count()} حساب ولي أمر.");

        return self::SUCCESS;
    }

    /** Whether the account opens with its own phone, in either way it is written. */
    private function opensWithItsPhone(Guardian $guardian): bool
    {
        if (! $guardian->password) {
            return false;
        }

        $digits = preg_replace('/\D/', '', (string) $guardian->phone);
        $local = str_starts_with($digits, '966') ? '0'.substr($digits, 3) : $digits;

        return Hash::check($digits, $guardian->password) || Hash::check($local, $guardian->password);
    }
}
