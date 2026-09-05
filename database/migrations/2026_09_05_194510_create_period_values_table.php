<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The character the circle is working on, and for how long.
     *
     * The calendar carried terms, holidays and exams — the shape of the year
     * and nothing of what the year is for. A value is the other half: صدق for
     * a fortnight, برّ الوالدين for a month, with the practice that goes with
     * it written beside it rather than left to be remembered.
     *
     * Held against a programme, or a cohort, or neither — and neither means
     * the whole academy is working on it together, which is usually the point.
     */
    public function up(): void
    {
        Schema::create('period_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained()->cascadeOnDelete();

            $table->date('starts_on');
            $table->date('ends_on');

            $table->string('title');

            // What to actually do about it. A value with no practice beside it
            // is a poster.
            $table->text('practice')->nullable();

            $table->text('evidence')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_values');
    }
};
