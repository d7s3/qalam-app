<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sight of an event, handed down one office at a time.
     *
     * The manager lets the supervisor see ten events; the supervisor may then
     * let the teacher see ten of them, or five, or none — never an eleventh.
     * Each grant records who made it, so the chain can be walked.
     *
     * Nothing cascades on revocation, and deliberately: a grant is read as
     * valid only while the office that made it can still see the event itself.
     * So the manager withdrawing the supervisor's sight withdraws the teacher's
     * with it, without a single row being deleted and without a sweep that
     * might miss one.
     */
    public function up(): void
    {
        Schema::create('calendar_event_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_calendar_event_id')->constrained()->cascadeOnDelete();

            // The office receiving sight.
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            // The office that handed it down. Null means the event's own owner,
            // which is where every chain begins.
            $table->foreignId('granted_by_role_id')->nullable()->constrained('roles')->cascadeOnDelete();

            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_calendar_event_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_grants');
    }
};
