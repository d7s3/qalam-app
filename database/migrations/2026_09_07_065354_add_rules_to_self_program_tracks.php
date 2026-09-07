<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two fields that do not behave like the other three.
     *
     * The programme's promise is that the student is free: he may do the whole
     * week on one day or spread it as he likes, and the daily share is only a
     * suggestion. Two fields are exceptions to that, and both are exceptions
     * for a reason rather than by preference.
     *
     * التحضير is preparation for a lesson. Preparing on Thursday for Sunday's
     * lesson is not preparation, so it is due on the day it was set for and no
     * other.
     *
     * والمحفوظ is memorised text, and memorising is not knowing until somebody
     * has heard it. The amount is not counted, and no points are given for it,
     * until the student says he has recited it — and if he says he has not,
     * nothing is written at all.
     *
     * Kept on the field rather than on the five keys, because the fields are a
     * table now and the academy may add a sixth that behaves either way.
     */
    public function up(): void
    {
        Schema::table('self_program_tracks', function (Blueprint $table) {
            $table->boolean('is_day_bound')->default(false);
            $table->boolean('needs_recitation_confirmation')->default(false);
        });

        DB::table('self_program_tracks')->where('key', 'tahdheer')->update(['is_day_bound' => true]);
        DB::table('self_program_tracks')->where('key', 'mahfoudh')->update(['needs_recitation_confirmation' => true]);
    }

    public function down(): void
    {
        Schema::table('self_program_tracks', function (Blueprint $table) {
            $table->dropColumn(['is_day_bound', 'needs_recitation_confirmation']);
        });
    }
};
