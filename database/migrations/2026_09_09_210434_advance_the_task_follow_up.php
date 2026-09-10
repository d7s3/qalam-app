<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a task needs to be followed rather than merely listed.
     *
     * The academy had tasks with a title, an owner and a date, which is a list.
     * Following work needs six more things, and they are added together because
     * they lean on each other: a step belongs to a task, an escalation needs a
     * stage to have stalled in, a template writes steps as well as titles.
     *
     * Nothing already written changes meaning. Every column added to `tasks`
     * has a default that reads exactly as the old behaviour did.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Where a task stands, beyond done and not-done. `status` keeps its
            // old words so nothing that reads it has to learn a new vocabulary;
            // this says which column of the board it sits in.
            $table->string('stage')->default('todo')->after('status');

            // Tasks made together — one asked of six teachers, or twelve raised
            // from a template — carry the same key so they can be seen as the
            // one act they were, while each is followed on its own.
            $table->string('batch_key')->nullable()->index()->after('stage');

            // The series a repeating task was born from, and the day it stands
            // for, so a fortnight's worth cannot be raised twice.
            $table->foreignId('task_series_id')->nullable()->constrained()->nullOnDelete();
            $table->date('occurs_on')->nullable();

            // When somebody senior was told this had gone past its date, so he
            // is told once rather than every night.
            $table->timestamp('escalated_at')->nullable();
        });

        /**
         * A repeating task, held as its pattern rather than as a thousand rows.
         *
         * The rows are made as their days come round, which keeps a year of
         * weekly reports out of the table until the year has happened, and lets
         * the pattern be changed without rewriting what already exists.
         */
        Schema::create('task_series', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('task_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('assigned_to_type')->nullable();
            $table->unsignedBigInteger('assigned_to_id')->nullable();

            // Or, instead of one person: everyone holding a role within a reach.
            $table->string('assign_to_role')->nullable();
            $table->string('assign_scope_type')->nullable();
            $table->json('assign_scope_ids')->nullable();

            // daily · weekly · monthly, and which day it falls on.
            $table->string('every')->default('weekly');
            $table->unsignedTinyInteger('on_weekday')->nullable();
            $table->unsignedTinyInteger('on_day')->nullable();

            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('remind_days_before')->nullable();
            $table->boolean('is_active')->default(true);

            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_on']);
        });

        /**
         * The steps inside a task.
         *
         * «In progress» means nothing on its own; a task with four steps and two
         * ticked means something to the man who has to answer for it.
         */
        Schema::create('task_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'sort_order']);
        });

        /** What was said about a task, by whom. */
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('author_type');
            $table->unsignedBigInteger('author_id');
            $table->text('body');
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });

        /**
         * What happened to a task, and who did it.
         *
         * Kept apart from the comments because it is written by the application
         * rather than by a person: a man may argue with a comment, and cannot
         * argue with the record of his own click.
         */
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });

        /**
         * A set of tasks the academy raises together, with dates written
         * relative to the day it is applied.
         *
         * «Opening a term» is twelve tasks with the same twelve owners every
         * time, and typing them out each term is how three of them come to be
         * forgotten in the term somebody was busy.
         */
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
        });

        Schema::create('task_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('task_category_id')->nullable()->constrained()->nullOnDelete();

            // Days from the day the template is applied. Negative is before it,
            // which is how preparation is written.
            $table->smallInteger('due_offset_days')->default(0);

            $table->string('assign_to_role')->nullable();
            $table->json('steps')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_template_items');
        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_steps');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_series_id');
            $table->dropColumn(['stage', 'batch_key', 'occurs_on', 'escalated_at']);
        });

        Schema::dropIfExists('task_series');
    }
};
