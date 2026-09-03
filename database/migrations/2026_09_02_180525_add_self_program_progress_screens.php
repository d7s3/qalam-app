<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<int, array{role: string, route: string, label: string, group: string}>
     */
    private array $screens = [
        ['role' => 'supervisor', 'route' => 'supervisor.self-program-progress', 'label' => 'تقدّم البرنامج الذاتي', 'group' => 'التقارير'],
        ['role' => 'manager', 'route' => 'manager.self-program-progress', 'label' => 'تقدّم البرنامج الذاتي', 'group' => 'التقارير'],
        ['role' => 'guardian', 'route' => 'guardian.self-program-progress', 'label' => 'البرنامج الذاتي', 'group' => 'المتابعة'],
    ];

    public function up(): void
    {
        foreach ($this->screens as $definition) {
            $role = Role::where('key', $definition['role'])->first();

            if (! $role) {
                continue;
            }

            $screen = Screen::create([
                'owner_role_id' => $role->id,
                'group_label' => $definition['group'],
                'route_name' => $definition['route'],
                'label' => $definition['label'],
                'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
                'is_protected' => false,
            ]);

            $screen->permissions()->create(['role_id' => $role->id]);
        }
    }

    public function down(): void
    {
        Screen::whereIn('route_name', array_column($this->screens, 'route'))->delete();
    }
};
