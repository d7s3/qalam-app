<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An attendance period said only which weekdays the circles met, and said it
 * for the whole academy at once. Stages keep different schedules, a term holds
 * make-up days and one-off closures, and a circle may meet more than once in a
 * day — none of which a weekday list can express.
 *
 * The four columns sit beside the weekdays as JSON, the shape this table
 * already uses for weekdays and shared_with, so nothing existing has to move.
 * An empty stage_ids keeps a period academy-wide, which is what the periods
 * already recorded mean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_calendar_events', function (Blueprint $table) {
            $table->json('stage_ids')->nullable()->after('weekdays');
            $table->json('extra_dates')->nullable()->after('stage_ids');
            $table->json('excluded_dates')->nullable()->after('extra_dates');
            $table->json('sessions')->nullable()->after('excluded_dates');
        });
    }

    public function down(): void
    {
        Schema::table('academic_calendar_events', function (Blueprint $table) {
            $table->dropColumn(['stage_ids', 'extra_dates', 'excluded_dates', 'sessions']);
        });
    }
};
