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
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            // The role whose route namespace this screen's URL lives under
            // (e.g. teacher.attendance is owned by the teacher role) — this is
            // about where the route is defined, not who may see it. Access is
            // granted independently via role_screen_permissions, so any role
            // can be permitted any screen from this global catalog.
            $table->foreignId('owner_role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('group_label');
            $table->string('route_name')->unique();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_protected')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
