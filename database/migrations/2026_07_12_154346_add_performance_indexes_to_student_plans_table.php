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
        Schema::table('student_plans', function (Blueprint $table) {
            $table->index('student_id');
            $table->index('teacher_id');
            $table->index(['is_approved', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_plans', function (Blueprint $table) {
            $table->dropIndex(['student_id']);
            $table->dropIndex(['teacher_id']);
            $table->dropIndex(['is_approved', 'created_at']);
        });
    }
};
