<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The page of the two commitment bars, opened for the three offices that
     * follow students, so an academy can close it for a role like any other.
     */
    public function up(): void
    {
        foreach (['manager', 'supervisor', 'teacher'] as $key) {
            $role = Role::where('key', $key)->first();

            if (! $role) {
                continue;
            }

            $screen = Screen::firstOrCreate(
                ['route_name' => "{$key}.commitment"],
                [
                    'owner_role_id' => $role->id,
                    'group_label' => 'الطلاب',
                    'label' => 'التزام الطلاب',
                    'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
                    'is_protected' => false,
                ],
            );

            $screen->permissions()->firstOrCreate(['role_id' => $role->id]);
        }
    }

    public function down(): void
    {
        Screen::whereIn('route_name', ['manager.commitment', 'supervisor.commitment', 'teacher.commitment'])->delete();
    }
};
