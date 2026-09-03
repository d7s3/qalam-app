<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A word before the day arrives.
     *
     * `remind_days_before` is how many days ahead its holder wants warning, and
     * `reminded_at` is the guard against telling him twice — a nightly run over
     * every open task would otherwise repeat itself every night until the day
     * came, and a reminder that arrives daily is one nobody reads.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('remind_days_before')->nullable();
            $table->timestamp('reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['remind_days_before', 'reminded_at']);
        });
    }
};
