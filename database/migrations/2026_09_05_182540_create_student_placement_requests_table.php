<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A teacher asking for a student, and the supervisor's answer.
     *
     * Placement used to be a teacher writing `circle_id` on a student directly,
     * from a pool that was every unplaced student in the academy: a teacher in
     * one programme could take a student who had registered for another, into
     * whichever of his own cohorts happened to be first in the table, and
     * nothing was written down about who had done it.
     *
     * The request is the record. It survives its own approval so the question
     * "who put this student here, and who agreed?" has an answer months later.
     */
    public function up(): void
    {
        Schema::create('student_placement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('circle_id')->constrained()->cascadeOnDelete();

            // pending until someone senior answers, then approved or rejected.
            $table->string('status')->default('pending');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // The supervisor's queue reads by status within his cohorts, and the
            // teacher's screen reads one student's own standing.
            $table->index(['status', 'circle_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_placement_requests');
    }
};
