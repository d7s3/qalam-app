<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What one track asks of the student in one week.
     *
     * `description` is what the student reads ("سورة الملك"), `target_amount`
     * and `unit` are what the progress bar measures against. The programme is
     * deliberately indifferent to the content itself — it tracks the doing.
     */
    public function up(): void
    {
        Schema::create('self_program_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_program_week_id')->constrained()->cascadeOnDelete();
            $table->string('track');
            $table->text('description')->nullable();
            $table->decimal('target_amount', 8, 2)->default(0);
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->unique(['self_program_week_id', 'track']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_program_items');
    }
};
