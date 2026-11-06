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
        Schema::create('student_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // login, login_failed, page, problem, signal
            $table->string('kind', 20);
            $table->string('route_name', 120)->nullable();
            $table->string('detail', 255)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'created_at']);
            $table->index(['kind', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_events');
    }
};
