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
        Schema::dropIfExists('hadith_path_days');

        Schema::create('hadith_path_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hadith_path_id')->constrained('hadith_paths')->onDelete('cascade');
            $table->integer('day_number');
            $table->date('date')->nullable();
            $table->string('day_name')->nullable();
            $table->string('memorize_type')->default('hadiths');
            $table->integer('memorize_amount')->default(1);
            $table->foreignId('from_hadith_id')->nullable()->constrained('hadiths')->onDelete('cascade');
            $table->foreignId('to_hadith_id')->nullable()->constrained('hadiths')->onDelete('cascade');
            $table->integer('from_line_number')->nullable();
            $table->integer('to_line_number')->nullable();
            $table->foreignId('review_from_hadith_id')->nullable()->constrained('hadiths')->onDelete('cascade');
            $table->foreignId('review_to_hadith_id')->nullable()->constrained('hadiths')->onDelete('cascade');
            $table->integer('review_from_line_number')->nullable();
            $table->integer('review_to_line_number')->nullable();
            $table->timestamps();
        });
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hadith_path_days');
    }
};
