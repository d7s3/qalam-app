<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Pages the permission layer could not see.
     *
     * `Access` reads an unregistered route as unrestricted — pages are written
     * before anyone thinks to govern them, and refusing what has not been
     * described would break the application every time one is added. The cost
     * is that a page nobody registered is a page the administrator cannot find
     * in order to close.
     *
     * Two of these are a whole feature: forms are governed for the supervisor
     * and were wide open for the manager and the teacher, the same screens
     * under different prefixes. The rest are detail views reached from a parent
     * that is governed — which protects the way in, but not the URL.
     *
     * Each is granted to the role that owns it, so nothing changes today beyond
     * the administrator now having a switch.
     *
     * @var array<int, array{role: string, route: string, label: string, group: string, icon: string}>
     */
    private const SCREENS = [
        ['role' => 'manager', 'route' => 'manager.forms', 'label' => 'إدارة النماذج', 'group' => 'المستخدمين', 'icon' => 'document-text'],
        ['role' => 'teacher', 'route' => 'teacher.forms', 'label' => 'إدارة النماذج', 'group' => 'المتابعة', 'icon' => 'document-text'],
        ['role' => 'manager', 'route' => 'manager.attendance-list', 'label' => 'حضور دفعة في يوم', 'group' => 'التقارير', 'icon' => 'calendar-days'],
        ['role' => 'manager', 'route' => 'manager.backup-browser', 'label' => 'تصفّح نسخة احتياطية', 'group' => 'إدارة النظام', 'icon' => 'archive-box'],
        ['role' => 'supervisor', 'route' => 'supervisor.stages.report', 'label' => 'تقرير برنامج', 'group' => 'المتابعة والتقارير', 'icon' => 'chart-bar'],
        ['role' => 'student', 'route' => 'student.plan-creator', 'label' => 'إنشاء خطتي', 'group' => 'البرنامج', 'icon' => 'pencil-square'],
        ['role' => 'teacher', 'route' => 'teacher.student-recitation-log', 'label' => 'سجل تسميع طالب', 'group' => 'المتابعة', 'icon' => 'book-open'],
        ['role' => 'guardian', 'route' => 'guardian.student', 'label' => 'صفحة الابن', 'group' => 'المتابعة', 'icon' => 'user'],
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
