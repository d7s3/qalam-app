<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('key', 'teacher')->first();

        if (! $role) {
            return;
        }

        $screen = Screen::create([
            'owner_role_id' => $role->id,
            'group_label' => 'البرنامج',
            'route_name' => 'teacher.self-program',
            'label' => 'البرنامج الذاتي',
            'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
            'is_protected' => false,
        ]);

        $screen->permissions()->create(['role_id' => $role->id]);
    }

    public function down(): void
    {
        Screen::where('route_name', 'teacher.self-program')->delete();
    }
};
