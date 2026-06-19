<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_plans', function (Blueprint $table) {
            $table->string('direction')->default('reverse')->change();
        });

        // Update existing rows
        DB::table('student_plans')->update(['direction' => 'reverse']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_plans', function (Blueprint $table) {
            $table->string('direction')->default('forward')->change();
        });
    }
};
