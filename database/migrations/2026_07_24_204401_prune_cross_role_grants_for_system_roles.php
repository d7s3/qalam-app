<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Removes inert cross-role grants: a system role that was granted a screen
     * owned by a different role. These never took effect (each system role sits
     * behind its own guard and renders a fixed sidebar) and only inflated the
     * role's permission count. Custom (non-system) roles keep their cross-role
     * grants — those intentionally render as "coming soon" staff-sidebar previews.
     */
    public function up(): void
    {
        $inertGrantIds = DB::table('role_screen_permissions as rsp')
            ->join('roles as r', 'r.id', '=', 'rsp.role_id')
            ->join('screens as s', 's.id', '=', 'rsp.screen_id')
            ->where('r.is_system', true)
            ->whereColumn('s.owner_role_id', '!=', 'rsp.role_id')
            ->pluck('rsp.id');

        DB::table('role_screen_permissions')->whereIn('id', $inertGrantIds)->delete();
    }

    /**
     * The pruned rows were non-functional, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
