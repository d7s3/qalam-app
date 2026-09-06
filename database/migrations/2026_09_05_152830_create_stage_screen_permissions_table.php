<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One programme's exception to what a role may see inside it.
     *
     * A stage is a programme — the word the academy uses — and the roles beneath
     * one are not the roles beneath another: a teacher in the memorisation
     * programme needs pages a teacher in the beginners' programme should not be
     * handed, and the manager wants to decide that per programme rather than for
     * every teacher at once.
     *
     * Only exceptions are stored, exactly as `user_screen_overrides` does. A
     * programme with no row for a screen inherits whatever the role was granted
     * centrally, so a newly created programme works the moment it exists and a
     * later central grant reaches every programme that did not refuse it.
     */
    public function up(): void
    {
        Schema::create('stage_screen_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();

            // true opens a page the role is not granted centrally; false closes
            // one it is. Absence is neither, and means "whatever the role says".
            $table->boolean('is_allowed');

            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['stage_id', 'role_id', 'screen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_screen_permissions');
    }
};
