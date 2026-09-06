<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How much of the academy this holding of a role reaches.
     *
     * The reach used to follow from the role alone — a manager saw everything,
     * a supervisor his programmes — so a tier between the two could not exist
     * without being written into the code. Put here, on the holding rather than
     * the role, it becomes something the administrator sets: a programme
     * director is the manager's role held over two programmes instead of all.
     *
     * Left null, the role decides as it always did, so nothing changes for
     * anyone already assigned.
     */
    public function up(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            // null = the role's own reach; otherwise 'all', 'stages' or 'circles'.
            $table->string('scope_type')->nullable();
            $table->json('scope_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropColumn(['scope_type', 'scope_ids']);
        });
    }
};
