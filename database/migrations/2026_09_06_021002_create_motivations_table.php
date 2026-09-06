<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the academy wants a student to meet when he opens the application.
     *
     * A verse, a hadith, a word of the salaf, a line of verse — gathered by
     * whoever has one: the teachers, the supervisors, the students themselves.
     *
     * The academy's condition is that nothing but the authentic and the good
     * is shown. No program can judge that, so nothing judges it here: a
     * contribution waits until somebody qualified says it may be shown, and its
     * attribution and its grading are fields on it rather than a promise. What
     * is not approved is simply never drawn.
     */
    public function up(): void
    {
        Schema::create('motivations', function (Blueprint $table) {
            $table->id();

            // ayah, hadith, athar (a word of the salaf), poetry.
            $table->string('kind');

            $table->text('text');

            // Where it is from — the sura and verse, the book and number, the
            // one it is attributed to.
            $table->string('source')->nullable();

            // صحيح, حسن, and nothing else is ever shown.
            $table->string('grade')->nullable();

            $table->foreignId('contributed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contributor_role')->nullable();

            // pending until reviewed, then approved or rejected.
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();

            $table->unsignedInteger('shown_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivations');
    }
};
