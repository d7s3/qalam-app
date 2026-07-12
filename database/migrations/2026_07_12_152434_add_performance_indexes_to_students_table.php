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
        Schema::table('students', function (Blueprint $table) {
            $table->index('circle_id');
            $table->index('guardian_id');
            $table->index('stage_id');
            $table->index('is_approved');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['circle_id']);
            $table->dropIndex(['guardian_id']);
            $table->dropIndex(['stage_id']);
            $table->dropIndex(['is_approved']);
            $table->dropIndex(['status']);
        });
    }
};
