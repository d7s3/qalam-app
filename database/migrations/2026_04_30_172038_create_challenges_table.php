<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safely drop the old wrong schema if it exists from the deleted duplicate migration
        if (Schema::hasTable('challenges') && ! Schema::hasColumn('challenges', 'start_date')) {
            Schema::dropIfExists('challenge_items');
            Schema::dropIfExists('challenges');
            DB::table('migrations')->where('migration', 'like', '%challenges%')->delete();
        }

        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->string('prize_type'); // 'financial', 'material'
            $table->text('prize_description');

            $table->string('status')->default('pending'); // pending, active, completed, failed, cancelled
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
