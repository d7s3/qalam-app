<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a day of the week actually holds, not only how much of it.
     *
     * The week carried one description per field and an amount, and the day's
     * share was arithmetic: the remainder divided by the working days left.
     * That answers "how much today" and cannot answer "what today" — and the
     * academy's own sheet is a grid whose Sunday says متممة الآجرومية while its
     * Tuesday says زاد المستقنع, with Wednesday empty on purpose.
     *
     * So the row that already carried the day's amount carries the day's
     * content beside it. And it gains a third scope: both keys null now means
     * the programme's own plan, which the supervisor writes once for everyone
     * reading that week — a cohort may then differ from it, and one student
     * from his cohort.
     */
    public function up(): void
    {
        Schema::table('self_program_day_overrides', function (Blueprint $table) {
            $table->string('content')->nullable()->after('amount');

            // A day may now be written for its content alone, with the amount
            // left to the arithmetic that always computed it.
            $table->decimal('amount', 8, 2)->nullable()->change();
        });

        // The rows written before this all belonged to a cohort or a student.
        DB::table('self_program_day_overrides')
            ->whereNull('scope_key')
            ->update(['scope_key' => 'c:0']);
    }

    public function down(): void
    {
        Schema::table('self_program_day_overrides', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
