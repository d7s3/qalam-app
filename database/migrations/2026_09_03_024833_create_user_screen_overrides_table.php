<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One person's exception to what his role may see.
     *
     * The role says what the job needs; this says what this holder of it gets,
     * and it wins. Two exceptions are possible and both are asked for in
     * practice: hiding a page from one teacher who should not have it, and
     * opening a page to one supervisor before his whole role is ready for it.
     *
     * Only exceptions are stored. A person with no row here is governed by his
     * role entirely, which is the ordinary case and costs nothing to express.
     */
    public function up(): void
    {
        Schema::create('user_screen_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();

            // true opens a page the role does not grant; false closes one it does.
            $table->boolean('is_allowed');

            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'screen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_screen_overrides');
    }
};
