<?php

use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a screen renders, when its name does not say.
     *
     * Most screens are a page of the same name, so these stay empty. Six of the
     * teacher's are tabs of one shell rather than pages of their own — and a
     * view of their name does exist, left from before the shell, showing
     * something else entirely. Without this, opening one of them through
     * seniority would quietly show the reader the old page instead of the real
     * one.
     */
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->string('view')->nullable();
            $table->json('view_data')->nullable();
        });

        foreach (['attendance', 'students', 'plan-creator', 'tasmeeh', 'leaderboards', 'grade-items'] as $tab) {
            Screen::where('route_name', 'teacher.'.$tab)->update([
                'view' => 'teacher.app-shell',
                'view_data' => ['initialTab' => $tab],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn(['view', 'view_data']);
        });
    }
};
