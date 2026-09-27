<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A response to a scored form (an mcq answer key, a reverse-aware likert)
     * carries its own grade, computed the moment it is submitted. Whatever a
     * form leaves for a human — a reading-comprehension answer, a paragraph —
     * is graded afterwards on the same response, which is why the manual side
     * needs its own "who and when" rather than reusing `is_processed`: that
     * column already means "turned into a student account", a different event
     * that a response may reach before, after, or never in relation to grading.
     */
    public function up(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->json('score')->nullable();
            $table->json('manual_scores')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->unsignedBigInteger('graded_by_id')->nullable();
            $table->string('graded_by_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->dropColumn(['score', 'manual_scores', 'graded_at', 'graded_by_id', 'graded_by_type']);
        });
    }
};
