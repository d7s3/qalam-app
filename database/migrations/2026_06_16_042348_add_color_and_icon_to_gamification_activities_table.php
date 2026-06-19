<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gamification_activities', function (Blueprint $table) {
            $table->string('color')->default('#10b981');
            $table->string('icon_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_activities', function (Blueprint $table) {
            $table->dropColumn(['color', 'icon_path']);
        });
    }
};
