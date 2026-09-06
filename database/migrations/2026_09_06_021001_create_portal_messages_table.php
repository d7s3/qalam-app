<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A word from one office to those at its level and beneath it.
     *
     * Not the messaging the application already has, which is a conversation
     * between two people. This is announced rather than sent: it meets its
     * reader when he opens the application, once, and is done.
     *
     * The sender may keep his name off it. A correction lands differently when
     * it is the office speaking rather than a person, and both are wanted.
     */
    public function up(): void
    {
        Schema::create('portal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

            // The office he spoke from, which is what bounds who he may reach.
            $table->string('sender_role');

            $table->string('title')->nullable();
            $table->text('body');

            $table->boolean('show_sender')->default(true);

            // A message may be given a life; most are simply shown until read.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->timestamps();

            $table->index(['sender_id', 'created_at']);
        });

        Schema::create('portal_message_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_message_id')->constrained()->cascadeOnDelete();

            // An office, or one person in it. Naming an office reaches everyone
            // holding it; naming a person reaches him alone.
            $table->string('role_key')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->timestamps();
        });

        Schema::create('portal_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            // Read once is read; the announcement does not return.
            $table->unique(['portal_message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_message_reads');
        Schema::dropIfExists('portal_message_audiences');
        Schema::dropIfExists('portal_messages');
    }
};
