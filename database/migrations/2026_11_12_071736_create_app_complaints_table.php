<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complaints about the platform sent to the Telegram bot, kept from the
     * first question so a sender can answer one at a time.
     */
    public function up(): void
    {
        Schema::create('app_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->index();
            $table->string('telegram_username')->nullable();
            $table->string('step')->default('name');
            $table->string('name')->nullable();
            $table->string('role')->nullable();
            $table->text('description')->nullable();
            $table->string('media_type')->nullable();
            $table->string('media_file_id')->nullable();
            $table->unsignedBigInteger('media_message_id')->nullable();
            $table->string('media_path')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('analysis')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_complaints');
    }
};
