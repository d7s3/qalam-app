<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Only those above a supervisor start with the report that judges him.
     */
    private array $roles = ['manager', 'supervisor'];

    public function up(): void
    {
        foreach (['manager', 'supervisor', 'teacher', 'guardian'] as $key) {
            $role = Role::where('key', $key)->first();

            if (! $role) {
                continue;
            }

            $screen = Screen::create([
                'owner_role_id' => $role->id,
                'group_label' => 'التقارير',
                'route_name' => $key.'.reports.supervision',
                'label' => 'تقرير متابعة المشرفين',
                'sort_order' => (Screen::where('owner_role_id', $role->id)->max('sort_order') ?? 0) + 1,
                'is_protected' => false,
            ]);

            if (in_array($key, $this->roles, true)) {
                $screen->permissions()->create(['role_id' => $role->id]);
            }
        }
    }

    public function down(): void
    {
        Screen::where('route_name', 'like', '%.reports.supervision')->delete();
    }
};
