<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two things a circle decides for itself about the self programme.
     *
     * `is_quranic` keeps hifz and review — which every circle had until now —
     * for the circles that actually memorise, and it is what lets a recitation
     * write the student's Quran wird for him. Existing circles all memorise, so
     * it defaults to true and nothing changes for them.
     *
     * `self_program_unlock_on_completion` lets the teacher say that finishing a
     * week early opens the next one, rather than waiting for its date.
     */
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->boolean('is_quranic')->default(true);
            $table->boolean('self_program_unlock_on_completion')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropColumn(['is_quranic', 'self_program_unlock_on_completion']);
        });
    }
};
