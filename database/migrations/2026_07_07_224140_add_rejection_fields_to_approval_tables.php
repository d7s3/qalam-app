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
        foreach (['students', 'teachers', 'supervisors', 'guardians'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('is_rejected')->default(false)->after('approved_by');
                $table->timestamp('rejected_at')->nullable()->after('is_rejected');
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('managers')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['students', 'teachers', 'supervisors', 'guardians'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('rejected_by');
                $table->dropColumn(['is_rejected', 'rejected_at']);
            });
        }
    }
};
