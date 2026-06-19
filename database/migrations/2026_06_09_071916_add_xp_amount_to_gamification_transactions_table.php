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
            $table->integer('xp_amount')->default(0)->after('amount');
        });

        DB::table('gamification_transactions')->update([
            'xp_amount' => DB::raw('amount'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_transactions', function (Blueprint $table) {
            $table->dropColumn('xp_amount');
        });
    }
};
