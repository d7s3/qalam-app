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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed

            $table->foreignId('task_category_id')->nullable()->constrained()->nullOnDelete();

            // Creator
            $table->unsignedBigInteger('created_by_id');
            $table->string('created_by_type');

            // Assignee
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->string('assigned_to_type')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
