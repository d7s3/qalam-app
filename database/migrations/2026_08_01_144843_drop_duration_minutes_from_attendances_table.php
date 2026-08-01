<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session length was a number the teacher typed on the attendance page, copied
 * onto every attendance row of that circle and day, and summed into a study-hours
 * figure. In practice it was left blank: seventeen rows of four thousand carried
 * a duration. Working hours now belong to the attendance period, where they are
 * stated once for a whole term rather than retyped every session, so the column
 * and the study-hours figure it fed go away.
 *
 * Rolling back restores the column, not the seventeen values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->integer('duration_minutes')->nullable();
        });
    }
};
