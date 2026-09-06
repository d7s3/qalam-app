<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const ROLES = ['manager', 'supervisor'];

    public function up(): void
    {
        foreach (self::ROLES as $key) {
            $role = Role::where('key', $key)->first();

            if (! $role || Screen::where('route_name', "{$key}.self-program-tracks")->exists()) {
                continue;
            }

            $screen = Screen::create([
                'owner_role_id' => $role->id,
                'group_label' => $key === 'manager' ? 'إدارة النظام' : 'إدارة النظام',
                'route_name' => "{$key}.self-program-tracks",
                'label' => 'مجالات البرنامج الذاتي',
                'icon' => 'adjustments-horizontal',
                'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
            ]);

            $screen->permissions()->create(['role_id' => $role->id]);
        }
    }

    public function down(): void
    {
        Screen::whereIn('route_name', array_map(fn ($k) => "{$k}.self-program-tracks", self::ROLES))->delete();
    }
};
