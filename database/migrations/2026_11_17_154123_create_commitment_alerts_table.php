<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitment_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained()->nullOnDelete();
            $table->date('week_starts_on');
            $table->date('week_ends_on');
            $table->decimal('plan_percent', 5, 1)->nullable();
            $table->decimal('self_percent', 5, 1)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->string('action')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'week_ends_on']);
            $table->index(['acted_at', 'escalated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitment_alerts');
    }
};
