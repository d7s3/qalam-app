<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give the override table a constraint the database can actually enforce.
     *
     * The unique index spanned `circle_id` and `student_id`, both nullable, and
     * SQL treats two NULLs as different values — so it never rejected a second
     * circle-wide override for the same day. Nothing produced one, because
     * Eloquent turns a null into `IS NULL` when it looks for the existing row,
     * but the backstop was resting on that and enforcing nothing itself.
     *
     * `scope_key` says the same thing in one non-null column: "c:5" for a whole
     * circle, "s:12" for one student.
     */
    public function up(): void
    {
        Schema::table('self_program_day_overrides', function (Blueprint $table) {
            $table->string('scope_key')->default('');
        });

        DB::table('self_program_day_overrides')->whereNotNull('student_id')
            ->update(['scope_key' => DB::raw("'s:' || student_id")]);
        DB::table('self_program_day_overrides')->whereNull('student_id')
            ->update(['scope_key' => DB::raw("'c:' || circle_id")]);

        Schema::table('self_program_day_overrides', function (Blueprint $table) {
            $table->dropUnique('self_program_day_overrides_unique');
            $table->unique(['self_program_item_id', 'scope_key', 'day_date'], 'self_program_day_overrides_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('self_program_day_overrides', function (Blueprint $table) {
            $table->dropUnique('self_program_day_overrides_scope_unique');
            $table->unique(['self_program_item_id', 'circle_id', 'student_id', 'day_date'], 'self_program_day_overrides_unique');
            $table->dropColumn('scope_key');
        });
    }
};
