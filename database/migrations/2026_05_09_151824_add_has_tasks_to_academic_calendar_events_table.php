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
        Schema::table('academic_calendar_events', function (Blueprint $table) {
            $table->boolean('has_tasks')->default(true)->after('is_visible');
        });

        // The user requested existing events to have has_tasks = false.
        DB::table('academic_calendar_events')->update(['has_tasks' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_calendar_events', function (Blueprint $table) {
            $table->dropColumn('has_tasks');
        });
    }
};
