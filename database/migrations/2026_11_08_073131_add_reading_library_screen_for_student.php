<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $student = Role::where('key', 'student')->first();

        if (! $student || Screen::where('route_name', 'student.library')->exists()) {
            return;
        }

        $screen = Screen::create([
            'owner_role_id' => $student->id,
            'group_label' => 'عام',
            'route_name' => 'student.library',
            'label' => 'مكتبتي',
            'icon' => 'book-open',
            'sort_order' => Screen::where('owner_role_id', $student->id)->max('sort_order') + 1,
        ]);

        $screen->permissions()->create(['role_id' => $student->id]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Screen::where('route_name', 'student.library')->delete();
    }
};
