<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Some environments ran the original create_student_hadith_plans_table
     * migration before it was edited in place to reference hadith_path_id
     * instead of hadith_id, leaving those databases with the stale column.
     * This repairs that drift by recreating the table with the correct shape.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('student_hadith_plans', 'hadith_id')) {
            return;
        }

        Schema::dropIfExists('student_hadith_plans');

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
        // Intentionally irreversible — the stale hadith_id column referenced
        // individual hadiths, a model the app no longer uses.
    }
};
