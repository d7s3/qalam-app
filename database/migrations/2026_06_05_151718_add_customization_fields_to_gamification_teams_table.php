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
        Schema::table('gamification_teams', function (Blueprint $table) {
            $table->string('color')->nullable()->after('name');
            $table->string('logo_path')->nullable()->after('color');
            $table->string('slogan')->nullable()->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_teams', function (Blueprint $table) {
            $table->dropColumn(['color', 'logo_path', 'slogan']);
        });
    }
};
