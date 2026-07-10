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
        Schema::create('disabled_role_pages', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('route');
            $table->foreignId('disabled_by')->nullable()->constrained('managers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['role', 'route']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disabled_role_pages');
    }
};
