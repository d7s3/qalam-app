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
        Schema::table('forms', function (Blueprint $table) {
            $table->string('public_report_token')->nullable()->unique();
        });

        // Generate tokens for existing forms
        foreach (Illuminate\Support\Facades\DB::table('forms')->get() as $form) {
            Illuminate\Support\Facades\DB::table('forms')
                ->where('id', $form->id)
                ->update(['public_report_token' => Illuminate\Support\Str::random(12)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('public_report_token');
        });
    }
};
