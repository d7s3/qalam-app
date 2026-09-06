<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What one educator noticed about one student on one day.
     *
     * A record of upbringing rather than of marks: he was downcast this week,
     * he stood up for a younger boy, his father travelled. The things that
     * explain a figure but are not one.
     *
     * The default is private, and that is the point. A teacher writes what he
     * would tell the supervisor and not what he would post on a wall, and he
     * writes it honestly only if he is the one who decides who reads it. He may
     * open a single note, or open everything he writes — and nothing is open
     * because somebody senior happens to be senior. The super administrator is
     * above this as he is above the rest, and that is the only exception.
     */
    public function up(): void
    {
        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            // The office he wrote from, which is what a share by office is read against.
            $table->string('author_role');

            $table->text('body');

            // The day it is about, which is not always the day it was written.
            $table->date('noted_on');

            // 'private' — the author and whoever he names. 'shared' — anyone who
            // may see the student at all.
            $table->string('visibility')->default('private');

            $table->timestamps();

            $table->index(['student_id', 'noted_on']);
            $table->index(['author_id', 'student_id']);
        });

        Schema::create('student_note_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_note_id')->constrained()->cascadeOnDelete();

            // An office, or one person. One note at a time, by the one who wrote it.
            $table->string('role_key')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_note_shares');
        Schema::dropIfExists('student_notes');
    }
};
