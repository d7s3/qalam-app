<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A teacher who is there for the memorisation and the review only.
     *
     * The obvious rule was to read it off his circles — a teacher of Quranic
     * circles only is a Quranic teacher. It cannot be read that way: every
     * circle in the academy is Quranic today, because `is_quranic` was added
     * with a default of true so that nothing already running would change. So
     * inferring it would have stripped pages from every teacher at once.
     *
     * It is set on the teacher instead, deliberately, and off until somebody
     * says so. His circles suggest it in the interface; they do not decide it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_recitation_only')->default(false)->after('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_recitation_only');
        });
    }
};
