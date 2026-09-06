<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Writing the programme, in every office's own sidebar.
     *
     * It belonged to the supervisor, and the offices above him reached it by
     * seniority — through a group that is collapsed by default, which is why
     * nobody found it. The academy asked for it pinned at all three levels.
     *
     * Named apart from the teacher's existing "البرنامج الذاتي", which is the
     * enrichment he writes for his own cohort. These are two different things
     * and two identical labels in one sidebar would be worse than hiding one.
     *
     * @var array<int, array{role: string, route: string, group: string}>
     */
    private const SCREENS = [
        ['role' => 'manager', 'route' => 'manager.self-program-weeks', 'group' => 'المستخدمين'],
        ['role' => 'teacher', 'route' => 'teacher.self-program-weeks', 'group' => 'البرنامج'],
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
                'label' => 'كتابة البرنامج الذاتي',
                'icon' => 'pencil-square',
                'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
            ]);

            $screen->permissions()->create(['role_id' => $role->id]);
        }

        // The supervisor's own sat under "إدارة النظام", which is where a
        // manager's switches live rather than where he writes the year.
        Screen::where('route_name', 'supervisor.self-program')
            ->update(['label' => 'كتابة البرنامج الذاتي', 'group_label' => 'العملية التعليمية']);
    }

    public function down(): void
    {
        Screen::whereIn('route_name', array_column(self::SCREENS, 'route'))->delete();

        Screen::where('route_name', 'supervisor.self-program')
            ->update(['label' => 'البرنامج الذاتي', 'group_label' => 'إدارة النظام']);
    }
};
