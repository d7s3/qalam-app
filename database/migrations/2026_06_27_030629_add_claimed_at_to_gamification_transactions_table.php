<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gamification_transactions', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('xp_amount');
        });

        // Backfill: every existing transaction is considered already claimed so
        // current balances and standings are not reset to zero.
        DB::table('gamification_transactions')->update(['claimed_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_transactions', function (Blueprint $table) {
            $table->dropColumn('claimed_at');
        });
    }
};
