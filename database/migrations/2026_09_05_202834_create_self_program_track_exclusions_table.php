<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A field set aside, for a while or for good.
     *
     * Only the setting aside is stored. A track runs unless something here says
     * it does not, so "this month the programme is the wird, the memorised and
     * the read" is three fields left alone and two rows written — and the month
     * after, the rows lapse and the programme is whole again without anybody
     * remembering to restore it.
     */
    public function up(): void
    {
        Schema::create('self_program_track_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_program_track_id')->constrained()->cascadeOnDelete();

            // Null means the whole academy sets it aside.
            $table->foreignId('stage_id')->nullable()->constrained()->cascadeOnDelete();

            // Both null means indefinitely, which is how a field is retired.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->string('reason')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['self_program_track_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_program_track_exclusions');
    }
};
