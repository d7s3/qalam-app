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
        Schema::create('student_hadith_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('hadith_path_id')->constrained('hadith_paths')->onDelete('cascade');
            $table->date('start_date');
            $table->string('status')->default('active'); // active, completed, suspended
            $table->string('created_by_role'); // supervisor, teacher
            $table->timestamps();

            // Indexes for fast lookup of active plans for students
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_hadith_plans');
    }
};
