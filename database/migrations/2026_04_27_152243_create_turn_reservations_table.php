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
        Schema::create('turn_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_reservation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('date');
            $table->integer('turn_number');
            $table->timestamps();

            $table->unique(['turn_reservation_session_id', 'student_id', 'date'], 'unique_student_reservation_date');
            $table->unique(['turn_reservation_session_id', 'date', 'turn_number'], 'unique_turn_number_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turn_reservations');
    }
};
