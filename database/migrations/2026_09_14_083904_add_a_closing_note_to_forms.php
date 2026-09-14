<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last thing a form says before somebody sends it.
 *
 * `public_intro` greets whoever opens the link; this is its mirror at the other
 * end, and it carries what an applicant must read before he sends rather than
 * after: what the thing costs, and that sending is not being accepted. Neither
 * belongs in `policy_text`, which is the privacy line and is set in small grey
 * type — fees printed there would be fees nobody read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->text('closing_note')->nullable()->after('public_intro');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('closing_note');
        });
    }
};
