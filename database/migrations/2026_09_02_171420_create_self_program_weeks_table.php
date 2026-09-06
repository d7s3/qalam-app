<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One week of the self programme.
     *
     * The self programme is authored a week at a time: a supervisor writes the
     * week for a stage, a teacher writes the enrichment week for a circle. Both
     * live here, told apart by `program_type`, because the two are the same
     * thing to a student — five tracks, an amount each, a week to do them in.
     */
    public function up(): void
    {
        Schema::create('self_program_weeks', function (Blueprint $table) {
            $table->id();

            // The stage carries the self programme, the circle the enrichment
            // one. Exactly one of the two is set on any given row.
            $table->foreignId('stage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('program_type')->default('self');
            $table->unsignedSmallInteger('week_number');
            $table->date('starts_on');
            $table->date('ends_on');

            // Supervisor for a self week, teacher for an enrichment one.
            $table->nullableMorphs('created_by');

            $table->timestamps();

            $table->unique(['stage_id', 'program_type', 'week_number'], 'self_program_weeks_stage_unique');
            $table->unique(['circle_id', 'program_type', 'week_number'], 'self_program_weeks_circle_unique');
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_program_weeks');
    }
};
