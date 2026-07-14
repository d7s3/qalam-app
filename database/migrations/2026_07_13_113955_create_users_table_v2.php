<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 of the 6-tables-into-one consolidation: purely additive.
     * Nothing reads or writes this table yet — the app keeps using
     * managers/supervisors/teachers/students/guardians/staffs unchanged
     * until the later cutover phase.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->datetime('two_factor_confirmed_at')->nullable();
            $table->string('phone')->nullable();
            $table->string('access_token')->nullable()->unique();

            // Staff-only: which custom dynamic role (app/Models/Role.php,
            // the manager-defined role/permission system) this staff
            // account holds. Named distinctly from the `role` column on
            // user_roles below to avoid confusing the two concepts.
            $table->foreignId('staff_role_id')->nullable()->constrained('roles')->nullOnDelete();

            // Teacher-only.
            $table->json('permissions')->nullable();

            // Student-only.
            $table->foreignId('circle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('national_id')->nullable();
            $table->string('nationality')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('joined_at')->nullable();
            $table->string('status')->nullable()->default('active');
            $table->string('avatar_path')->nullable();

            $table->timestamps();

            $table->index('circle_id');
            $table->index('guardian_id');
            $table->index('stage_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
