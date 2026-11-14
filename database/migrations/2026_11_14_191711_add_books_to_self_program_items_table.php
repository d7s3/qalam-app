<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The books a reading field asks for, each with the pages from and to —
     * asked for by Faisal, 14 Nov 2026, so the student sees them every week.
     */
    public function up(): void
    {
        Schema::table('self_program_items', function (Blueprint $table) {
            $table->json('books')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('self_program_items', function (Blueprint $table) {
            $table->dropColumn('books');
        });
    }
};
