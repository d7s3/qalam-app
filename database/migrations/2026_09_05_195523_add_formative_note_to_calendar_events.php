<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A word to the student about the day, beside the name of it.
     *
     * `description` is the administrator writing to himself — "الفصل الدراسي
     * الأول". This is what the day is for, in the words the student should
     * read: why an exam week is a mercy, what a closure is for, what to make of
     * the ten of Dhul-Hijjah. An event that is only a name and a colour teaches
     * nothing by being on a calendar.
     */
    public function up(): void
    {
        Schema::table('academic_calendar_events', function (Blueprint $table) {
            $table->text('formative_note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('academic_calendar_events', function (Blueprint $table) {
            $table->dropColumn('formative_note');
        });
    }
};
