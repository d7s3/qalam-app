<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether this person is still using the code he was handed.
     *
     * An account made by an administrator is opened with a starting code the
     * academy gives out — the same one each time, so it can be said over the
     * phone without anybody writing anything down. That is only safe while it
     * cannot be used twice: the moment he signs in with it, the application
     * takes him to a screen he cannot leave until he has chosen his own.
     *
     * False for everyone who already has an account, because everyone who
     * already has one chose his own password when he registered.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
