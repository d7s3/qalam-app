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
        Schema::create('ode_path_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ode_path_id')->constrained('ode_paths')->onDelete('cascade');
            $table->integer('day_number');
            $table->date('date')->nullable();
            $table->string('day_name')->nullable();
            $table->integer('from_verse_number')->nullable();
            $table->integer('to_verse_number')->nullable();
            $table->integer('review_from_verse_number')->nullable();
            $table->integer('review_to_verse_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ode_path_days');
    }
};
