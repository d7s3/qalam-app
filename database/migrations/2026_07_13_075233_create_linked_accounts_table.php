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
        Schema::create('linked_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('first_guard');
            $table->unsignedBigInteger('first_id');
            $table->string('second_guard');
            $table->unsignedBigInteger('second_id');
            $table->foreignId('linked_by')->nullable()->constrained('managers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['first_guard', 'first_id', 'second_guard', 'second_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('linked_accounts');
    }
};
