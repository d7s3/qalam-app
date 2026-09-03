<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a task was finished, and by whom.
     *
     * A task carried a due date and a status, and nothing between them: it was
     * either pending or completed, with no record of when. So whether anyone
     * ever met a deadline could not be asked, and judging a teacher or a
     * supervisor on their tasks was not a report waiting to be written — it was
     * a question the data could not answer.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->index();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completed_at');
        });
    }
};
