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
        Schema::create('hadith_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hadith_text_id')->constrained('hadith_texts')->onDelete('cascade');
            $table->string('name');
            $table->string('memorize_type'); // lines or hadiths
            $table->integer('memorize_amount');
            $table->date('start_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hadith_paths');
    }
};
