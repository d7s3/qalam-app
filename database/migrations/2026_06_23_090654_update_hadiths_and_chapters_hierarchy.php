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
        Schema::table('hadith_chapters', function (Blueprint $table) {
            $table->dropUnique('hadith_chapters_name_unique');
            $table->foreignId('hadith_text_id')->nullable()->constrained('hadith_texts')->onDelete('cascade');
            $table->unique(['hadith_text_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hadith_chapters', function (Blueprint $table) {
            $table->dropUnique(['hadith_text_id', 'name']);
            $table->dropForeign(['hadith_text_id']);
            $table->dropColumn('hadith_text_id');
            $table->unique('name');
        });
    }
};
