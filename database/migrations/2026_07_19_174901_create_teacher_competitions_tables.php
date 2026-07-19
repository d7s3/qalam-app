<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher competition is entirely independent of the student
 * Leaderboard/gamification system — separate tables, no shared FKs — so it
 * can be built, changed, or removed without any risk to the student
 * competition feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_competitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('teacher_competition_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teacher_competition_id', 'teacher_id']);
        });

        Schema::create('teacher_competition_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_competition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('max_points')->default(10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('teacher_competition_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('teacher_competition_criteria')->cascadeOnDelete();
            $table->unsignedInteger('score')->nullable();
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_competition_scores');
        Schema::dropIfExists('teacher_competition_criteria');
        Schema::dropIfExists('teacher_competition_participants');
        Schema::dropIfExists('teacher_competitions');
    }
};
