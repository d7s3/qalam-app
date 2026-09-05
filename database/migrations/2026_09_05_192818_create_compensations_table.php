<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a person still owes from a day that has passed.
     *
     * A loss that is only reported is a loss nobody does anything about. This
     * is the lane it moves into: it stays open, week after week, and travels
     * with him until it is made good — and it is kept apart from the new week's
     * work on purpose, so meeting this week in full is not spoiled by what was
     * missed last week, and what was missed is not quietly forgiven by a good
     * week either.
     *
     * Raised only when the absence is known — somebody marked him away, or away
     * with an excuse. An occurrence nobody answered for at all is not yet a
     * debt; it is a question, and the day screen asks it.
     */
    public function up(): void
    {
        Schema::create('compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'formative' — a meeting to sit again. 'scientific' — work to do.
            // They are not made good by the same thing.
            $table->string('kind');

            $table->string('label');
            $table->text('detail')->nullable();

            // Where it came from, so the same miss is never owed twice.
            $table->string('source_key');
            $table->date('original_date');

            $table->string('status')->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_key']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensations');
    }
};
