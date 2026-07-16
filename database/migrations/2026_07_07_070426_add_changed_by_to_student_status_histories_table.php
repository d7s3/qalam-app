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
        Schema::table('student_status_histories', function (Blueprint $table) {
            $table->string('changed_by_role')->nullable()->after('notes');
            $table->string('changed_by_name')->nullable()->after('changed_by_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_status_histories', function (Blueprint $table) {
            $table->dropColumn(['changed_by_role', 'changed_by_name']);
        });
    }
};
