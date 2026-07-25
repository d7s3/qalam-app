<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * An API key for one AI provider, entered from the manager's AI settings page
 * rather than the environment file. The key is encrypted with the application
 * key, so it is unreadable in a database dump or a downloaded backup.
 */
#[Hidden(['api_key'])]
class AiProviderCredential extends Model
{
    protected $fillable = ['provider', 'api_key', 'base_url'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }

    /**
     * The last four characters, for confirming which key is stored without
     * ever sending the key itself to the browser.
     */
    public function maskedKey(): ?string
    {
        if (blank($this->api_key)) {
            return null;
        }

        return '••••••••'.mb_substr($this->api_key, -4);
    }
}
