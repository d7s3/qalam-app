<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<int, array{role: string, route: string, label: string, group: string, icon: string}>
     */
    private const SCREENS = [
        ['role' => 'manager', 'route' => 'manager.portal', 'label' => 'بوابة الرسائل', 'group' => 'المستخدمين', 'icon' => 'megaphone'],
        ['role' => 'supervisor', 'route' => 'supervisor.portal', 'label' => 'بوابة الرسائل', 'group' => 'المتابعة والتقارير', 'icon' => 'megaphone'],
        ['role' => 'teacher', 'route' => 'teacher.portal', 'label' => 'بوابة الرسائل', 'group' => 'المتابعة', 'icon' => 'megaphone'],
        ['role' => 'manager', 'route' => 'manager.motivations', 'label' => 'مستودع الشواهد', 'group' => 'المستخدمين', 'icon' => 'sparkles'],
        ['role' => 'supervisor', 'route' => 'supervisor.motivations', 'label' => 'مستودع الشواهد', 'group' => 'المتابعة والتقارير', 'icon' => 'sparkles'],
        ['role' => 'teacher', 'route' => 'teacher.motivations', 'label' => 'مستودع الشواهد', 'group' => 'المتابعة', 'icon' => 'sparkles'],
        ['role' => 'student', 'route' => 'student.motivations', 'label' => 'مستودع الشواهد', 'group' => 'البرنامج', 'icon' => 'sparkles'],
    ];

    public function up(): void
    {
        foreach (self::SCREENS as $entry) {
            $role = Role::where('key', $entry['role'])->first();

            if (! $role || Screen::where('route_name', $entry['route'])->exists()) {
                continue;
            }

            $screen = Screen::create([
                'owner_role_id' => $role->id,
                'group_label' => $entry['group'],
                'route_name' => $entry['route'],
                'label' => $entry['label'],
                'icon' => $entry['icon'],
                'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
            ]);

            $screen->permissions()->create(['role_id' => $role->id]);
        }
    }

    public function down(): void
    {
        Screen::whereIn('route_name', array_column(self::SCREENS, 'route'))->delete();
    }
};
