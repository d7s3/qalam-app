<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A form's second colour.
     *
     * A programme's identity is rarely one colour. نوابغ's poster alternates a
     * teal and a coral all the way down — the badge, the name, the five track
     * numbers — and a page in a single tone reads as a different programme
     * beside it.
     *
     * The second was first derived from the first by matching a string, which
     * worked until the seeder was run again with a different value and both
     * colours quietly became the same one. A colour somebody chose belongs in a
     * column, not in a condition.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('accent_color')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('accent_color');
        });
    }
};
