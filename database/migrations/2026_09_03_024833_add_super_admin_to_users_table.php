<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The account that can never be locked out.
     *
     * Deliberately a mark on the person, not a role in the table. A role can be
     * edited, and the first time an administrator switches off the permissions
     * screen for his own role — by accident or by experiment — he bolts the door
     * from the inside and only a programmer can open it. A flag the permission
     * check consults before anything else cannot be revoked that way.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
