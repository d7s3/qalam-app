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
        ['role' => 'student', 'route' => 'student.self-program', 'label' => 'البرنامج الذاتي', 'group' => 'البرنامج'],
        ['role' => 'supervisor', 'route' => 'supervisor.self-program', 'label' => 'البرنامج الذاتي', 'group' => 'إدارة النظام'],
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
