<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            // Shown to the manager who set it, so he can tell it to the student.
            $table->string('unlock_code', 50)->nullable();
            $table->nullableMorphs('sent_by');
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'opened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_notices');
    }
};
