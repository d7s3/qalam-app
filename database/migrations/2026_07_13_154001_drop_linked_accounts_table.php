<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The LinkedAccount feature (cross-account role switching) is obsolete now
 * that all of a person's roles live on one `users` row via `user_roles` —
 * "switching role" is just logging the same row into another guard, nothing
 * to link. A new migration rather than editing the original create migration,
 * per the project's migration-immutability convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('linked_accounts');
    }

    public function down(): void
    {
        Schema::create('linked_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('first_guard');
            $table->unsignedBigInteger('first_id');
            $table->string('second_guard');
            $table->unsignedBigInteger('second_id');
            $table->foreignId('linked_by')->nullable()->constrained('managers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['first_guard', 'first_id', 'second_guard', 'second_id']);
        });
    }
};
