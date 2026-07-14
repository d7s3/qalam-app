<?php

use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

/**
 * Reverses 2026_07_13_075559_add_linked_accounts_screen_for_manager — the
 * standalone "ربط الحسابات" page is removed now that all roles for a person
 * live on one `users` row (see `user_roles`), making cross-account linking
 * obsolete. A new migration rather than editing the original, since that one
 * may already be applied elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Screen::where('route_name', 'manager.linked-accounts')->delete();
    }

    public function down(): void
    {
        // Not meaningfully reversible — re-run the original migration's up() if needed.
    }
};
