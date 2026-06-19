
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
        Schema::create('odes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ode_verses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ode_id')->constrained('odes')->onDelete('cascade');
            $table->integer('verse_number');
            $table->string('sadr'); // First half
            $table->string('ajuz'); // Second half
            $table->timestamps();

            // Add index for fast querying of verses for a specific ode in order
            $table->unique(['ode_id', 'verse_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ode_verses');
        Schema::dropIfExists('odes');
    }
};
