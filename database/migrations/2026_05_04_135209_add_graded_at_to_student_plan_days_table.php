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
        Schema::table('student_plan_days', function (Blueprint $table) {
            $table->timestamp('hifz_graded_at')->nullable()->after('hifz_achievement')
                ->comment('Timestamp when hifz achievement was last recorded');
            $table->timestamp('review_graded_at')->nullable()->after('review_achievement')
                ->comment('Timestamp when review achievement was last recorded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_plan_days', function (Blueprint $table) {
            $table->dropColumn(['hifz_graded_at', 'review_graded_at']);
        });
    }
};
