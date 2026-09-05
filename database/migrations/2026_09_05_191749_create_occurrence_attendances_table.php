<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who turned up to a named thing on a named day.
     *
     * The register the academy already keeps answers "was Salim in his cohort
     * on the fifth?" — one row per student per cohort per day. It cannot answer
     * "did he come to Tuesday's lesson?", because a day holding a lesson, a
     * meeting and a recitation circle holds one absence between them and never
     * says which was missed.
     *
     * An occurrence is a calendar event on one of its days. Attendance hangs on
     * that rather than on the date, which is what makes the missed thing
     * nameable — and everyone records his own, the teacher and the supervisor
     * and the manager alongside the student.
     *
     * The daily register is untouched and still runs; this answers a different
     * question beside it.
     */
    public function up(): void
    {
        Schema::create('occurrence_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_calendar_event_id')->constrained()->cascadeOnDelete();

            // The occurrence is the event and the day together; the event alone
            // spans many.
            $table->date('date');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The capacity he attended in — the same person may hold several.
            $table->string('role');

            $table->string('status')->default('present');
            $table->boolean('self_recorded')->default(true);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            // One answer per person per occurrence.
            $table->unique(['academic_calendar_event_id', 'date', 'user_id'], 'occurrence_attendance_unique');

            // "What did he attend, and what did he miss" reads by person and day.
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrence_attendances');
    }
};
