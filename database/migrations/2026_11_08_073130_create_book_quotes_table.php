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
        Schema::create('book_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_book_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->unsignedSmallInteger('page')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_quotes');
    }
};
