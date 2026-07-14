<?php

use App\Models\DisabledRolePage;
use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Snapshot of the pre-existing App\Support\RolePages::REGISTRY content at
     * the time this migration was written, plus a manager catalog mirrored
     * from resources/views/manager/sidebar-nav.blade.php (manager wasn't part
     * of the old REGISTRY — its sidebar was ungated). A migration must stay a
     * self-contained historical snapshot, so this does not reference the
     * (now rewritten) RolePages class.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const CATALOG = [
        'teacher' => [
            'عام' => [
                'teacher.dashboard' => 'الرئيسية',
                'teacher.messages' => 'الرسائل',
            ],
            'الخطط القرآنية' => [
                'teacher.students' => 'إدارة الطلاب',
                'teacher.plan-creator' => 'إنشاء خطة طالب',
                'teacher.student-plans' => 'عرض الخطط المنشأة',
                'teacher.tasmeeh' => 'التسميع والمتابعة',
                'teacher.pairs' => 'التسميع المتبادل',
            ],
            'خطط المنظومات' => [
                'teacher.ode-plans' => 'عرض الخطط المنشأة',
            ],
            'التحفيز والمنافسة' => [
                'teacher.leaderboards' => 'مسابقات الحلقة',
            ],
            'الاختبارات' => [
                'teacher.student-exams' => 'اختبارات الطلاب',
            ],
            'التحضير' => [
                'teacher.attendance' => 'سجل الحضور',
                'teacher.discipline' => 'الانضباط الحضوري',
                'teacher.quranic-discipline' => 'الانضباط القرآني',
                'teacher.exceeded-limits' => 'لائحة التجاوزات',
            ],
        ],
        'supervisor' => [
            'عام' => [
                'supervisor.dashboard' => 'الرئيسية',
                'supervisor.messages' => 'الرسائل',
            ],
            'العملية التعليمية' => [
                'supervisor.circles' => 'الحلقات',
                'supervisor.students' => 'الطلاب',
                'supervisor.teachers' => 'المعلمون',
            ],
            'المحتوى العلمي' => [
                'supervisor.odes' => 'إدارة المنظومات',
                'supervisor.odes.paths' => 'مسارات حفظ المنظومات',
                'supervisor.hadiths' => 'إدارة الأحاديث',
                'supervisor.hadiths.paths' => 'مسارات حفظ المتون',
                'supervisor.odes.plans' => 'خطط المنظومات المنشأة',
            ],
            'التلعيب والمسابقات' => [
                'supervisor.competitions' => 'المسابقات',
            ],
            'المتابعة والتقارير' => [
                'supervisor.yearly-attendance' => 'متابعة الحلقات السنوي',
                'supervisor.academic-calendar' => 'التقويم الأكاديمي',
                'supervisor.tasks' => 'المهام',
                'supervisor.exceeded-limits' => 'لائحة التجاوزات',
            ],
            'إدارة النظام' => [
                'supervisor.whatsapp-settings' => 'إعدادات الواتساب',
                'supervisor.forms' => 'إدارة النماذج',
            ],
        ],
        'guardian' => [
            'عام' => [
                'guardian.dashboard' => 'الرئيسية',
                'guardian.messages' => 'الرسائل',
                'guardian.challenges' => 'المكافآت التحفيزية',
            ],
        ],
        'student' => [
            'عام' => [
                'student.dashboard' => 'الرئيسية',
                'student.messages' => 'الرسائل',
            ],
            'التعلم والمتابعة' => [
                'student.plan' => 'مساري القرآني',
                'student.hifz' => 'الحفظ',
                'student.review' => 'المراجعة',
                'student.exams' => 'الاختبارات',
                'student.calendar' => 'التقويم',
                'student.attendance' => 'سجل الانضباط',
                'student.reports' => 'التقارير',
            ],
        ],
        'manager' => [
            'عام' => [
                'manager.dashboard' => 'الرئيسية',
                'manager.messages' => 'الرسائل',
                'manager.stages' => 'المراحل التعليمية',
                'manager.circles' => 'الحلقات',
            ],
            'المستخدمين' => [
                'manager.supervisors' => 'المشرفون',
                'manager.teachers' => 'المعلمون',
                'manager.students' => 'الطلاب',
                'manager.guardians' => 'الأوصياء',
                'manager.pending-approvals' => 'طلبات التسجيل',
            ],
            'الاختبارات' => [
                'manager.exam-levels' => 'مستويات الاختبارات',
                'manager.student-exams' => 'اختبارات الطلاب',
            ],
            'التقارير' => [
                'manager.attendance-reports' => 'تقارير الحضور والغياب',
                'manager.yearly-attendance' => 'متابعة الحلقات السنوي',
                'manager.academic-calendar' => 'التقويم الأكاديمي',
                'manager.tasks' => 'المهام',
                'manager.quranic-achievement' => 'تقرير الإنجاز القرآني',
                'manager.exceeded-limits' => 'لائحة التجاوزات',
            ],
            'التحليل' => [
                'manager.ai-analysis' => 'التحليل الذكي',
            ],
            'بيانات المصحف' => [
                'manager.quran-editor' => 'محرر الأسطر',
            ],
            'إدارة النظام' => [
                'manager.settings' => 'إعدادات الانضباط',
                'manager.whatsapp-settings' => 'إعدادات الواتساب',
                'manager.api-docs' => 'توثيق الـ API',
                'manager.role-permissions' => 'صلاحيات الصفحات',
            ],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roles = [];

        foreach (array_keys(self::CATALOG) as $roleKey) {
            $roles[$roleKey] = Role::create([
                'key' => $roleKey,
                'label' => $this->labelFor($roleKey),
                'guard_name' => $roleKey,
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        foreach (self::CATALOG as $roleKey => $groups) {
            $role = $roles[$roleKey];
            $sortOrder = 0;

            foreach ($groups as $groupLabel => $pages) {
                foreach ($pages as $routeName => $label) {
                    $screen = Screen::create([
                        'owner_role_id' => $role->id,
                        'group_label' => $groupLabel,
                        'route_name' => $routeName,
                        'label' => $label,
                        'sort_order' => $sortOrder++,
                        'is_protected' => str_ends_with($routeName, '.dashboard') || $routeName === 'manager.role-permissions',
                    ]);

                    // Preserve today's exact visible/hidden state: enabled unless
                    // a disabled_role_pages row already turned it off for this role.
                    $isDisabledToday = DisabledRolePage::where('role', $roleKey)
                        ->where('route', $routeName)
                        ->exists();

                    if (! $isDisabledToday) {
                        $screen->permissions()->create(['role_id' => $role->id]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::whereIn('key', ['manager', 'supervisor', 'teacher', 'student', 'guardian'])->delete();
    }

    private function labelFor(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'المدير',
            'supervisor' => 'المشرف',
            'teacher' => 'المعلم',
            'student' => 'الطالب',
            'guardian' => 'ولي الأمر',
            default => $roleKey,
        };
    }
};
