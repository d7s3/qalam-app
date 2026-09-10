<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The two screens that follow work rather than list it.
     *
     * Registered so they sit inside the permission layer like every other page:
     * an academy that does not want a board can close it for a role, and a
     * programme that does can open it for its own.
     */
    private const SCREENS = [
        'task-board' => 'لوحة المهام',
        'task-automation' => 'المهام التلقائية',
    ];

    public function up(): void
    {
        foreach (['manager', 'supervisor', 'teacher'] as $key) {
            $role = Role::where('key', $key)->first();

            if (! $role) {
                continue;
            }

            foreach (self::SCREENS as $slug => $label) {
                $screen = Screen::firstOrCreate(
                    ['route_name' => "{$key}.{$slug}"],
                    [
                        'owner_role_id' => $role->id,
                        'group_label' => 'المهام',
                        'label' => $label,
                        'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
                        'is_protected' => false,
                    ],
                );

                $screen->permissions()->firstOrCreate(['role_id' => $role->id]);
            }
        }
    }

    public function down(): void
    {
        foreach (['manager', 'supervisor', 'teacher'] as $key) {
            foreach (array_keys(self::SCREENS) as $slug) {
                Screen::where('route_name', "{$key}.{$slug}")->delete();
            }
        }
    }
};
