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
            $table->string('national_id')->nullable()->after('phone');
            $table->string('nationality')->nullable()->after('national_id');
            $table->foreignId('stage_id')->nullable()->after('circle_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_id');
            $table->dropColumn(['national_id', 'nationality']);
        });
    }
};
