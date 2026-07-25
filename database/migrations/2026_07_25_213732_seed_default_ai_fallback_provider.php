<?php

use App\Models\Setting;
use App\Support\AiSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * Moving the provider choice onto the settings page turned the previously
 * hardcoded Gemini-then-DeepSeek failover into "no fallback at all", because
 * an unset setting means none. This restores that safety net for deployments
 * that already have a DeepSeek key, and only when the manager has not made a
 * choice of their own.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Setting::getVal(AiSettings::FALLBACK_PROVIDER_KEY) !== null) {
            return;
        }

        if (blank(config('ai.providers.deepseek.key')) || AiSettings::provider() === 'deepseek') {
            return;
        }

        Setting::setVal(AiSettings::FALLBACK_PROVIDER_KEY, 'deepseek');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::where('key', AiSettings::FALLBACK_PROVIDER_KEY)
            ->where('value', 'deepseek')
            ->delete();
    }
};
