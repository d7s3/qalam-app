<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const ROLES = ['manager' => 'المستخدمين', 'supervisor' => 'المتابعة والتقارير', 'teacher' => 'المتابعة'];

    public function up(): void
    {
        foreach (self::ROLES as $key => $group) {
            $role = Role::where('key', $key)->first();

            if (! $role || Screen::where('route_name', "{$key}.student-log")->exists()) {
                continue;
            }

            $screen = Screen::create([
                'owner_role_id' => $role->id,
                'group_label' => $group,
                'route_name' => "{$key}.student-log",
                'label' => 'السجل التربوي',
                'icon' => 'book-open',
                'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
            ]);

            $screen->permissions()->create(['role_id' => $role->id]);
        }
    }

    public function down(): void
    {
        Screen::whereIn('route_name', array_map(fn ($k) => "{$k}.student-log", array_keys(self::ROLES)))->delete();
    }
};
