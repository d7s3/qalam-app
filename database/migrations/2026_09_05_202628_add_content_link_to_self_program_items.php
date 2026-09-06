<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The thing itself, not just its name.
     *
     * A week said "منظومة البيقونية، عشرة أبيات" and left the student to find
     * the poem. The supervisor writing the week has the text in front of him;
     * this is where he puts it, and the student opens what he is asked for
     * instead of hunting for it.
     *
     * The Quran wird needs none of this — the mushaf is in the application
     * already, and a link out of it would be a step backwards.
     */
    public function up(): void
    {
        Schema::table('self_program_items', function (Blueprint $table) {
            $table->string('content_url', 2048)->nullable()->after('description');
            $table->string('content_label')->nullable()->after('content_url');
        });
    }

    public function down(): void
    {
        Schema::table('self_program_items', function (Blueprint $table) {
            $table->dropColumn(['content_url', 'content_label']);
        });
    }
};
