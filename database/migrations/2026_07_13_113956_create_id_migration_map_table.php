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
        Schema::create('id_migration_map', function (Blueprint $table) {
            $table->id();

            // Which legacy table/row this maps from, e.g. ('teachers', 6).
            $table->string('old_table');
            $table->unsignedBigInteger('old_id');

            $table->foreignId('new_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['old_table', 'old_id']);
            $table->index('new_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_migration_map');
    }
};
