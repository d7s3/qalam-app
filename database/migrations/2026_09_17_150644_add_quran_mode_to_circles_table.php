<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split «حلقة قرآنية» into the two questions it was answering at once.
 *
 * `is_quranic` decided both whether a cohort recites and whether it keeps a
 * day-by-day plan of ayah ranges, so a حلقة that hears its students without
 * writing such a plan had nowhere to stand. The mode says which of the three
 * it is, and `is_quranic` stays as the answer to the first question alone —
 * every reader of it goes on reading it correctly.
 *
 * Nothing changes for any existing cohort. «On» becomes «تفصيلي», which is what
 * it was. «Off» becomes «إجمالي» — not «بلا»: a cohort with the switch off still
 * had the wird and still wrote it itself, and that is precisely what «إجمالي»
 * is. «بلا برنامج قرآني» is the state this column adds, and nobody is in it
 * until somebody chooses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->string('quran_mode', 16)->default('detailed')->after('is_quranic');
        });

        DB::table('circles')->update([
            'quran_mode' => DB::raw("case when is_quranic = 1 then 'detailed' else 'summary' end"),
        ]);
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropColumn('quran_mode');
        });
    }
};
