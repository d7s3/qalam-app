<?php

namespace App\Support;

use App\Models\DisabledRolePage;

class RolePages
{
    /**
     * Every toggleable sidebar page per role, grouped for the manager's
     * permissions screen. Route names double as the permission key — a
     * disabled entry also blocks any route nested under it (e.g. disabling
     * "teacher.leaderboards" blocks "teacher.leaderboards.grade" too).
     */
    public const REGISTRY = [
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
    ];

    public static function roles(): array
    {
        return array_keys(self::REGISTRY);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function pagesFor(string $role): array
    {
        return self::REGISTRY[$role] ?? [];
    }

    public static function isEnabled(string $role, ?string $routeName): bool
    {
        if (! $routeName || self::isProtected($routeName)) {
            return true;
        }

        $match = self::longestMatch($role, $routeName);

        if (! $match) {
            return true;
        }

        return ! in_array($match, self::disabledSet($role), true);
    }

    /**
     * The dashboard is where a user lands right after login — disabling it
     * would strand them with nowhere else to go, so it can never be turned off.
     */
    public static function isProtected(string $routeName): bool
    {
        return str_ends_with($routeName, '.dashboard');
    }

    protected static function longestMatch(string $role, string $routeName): ?string
    {
        $registered = collect(self::REGISTRY[$role] ?? [])->collapse()->keys();

        return $registered
            ->filter(fn ($route) => $routeName === $route || str_starts_with($routeName, $route.'.'))
            ->sortByDesc(fn ($route) => strlen($route))
            ->first();
    }

    /**
     * Memoized per request: `isEnabled()` is called once per middleware pass
     * plus once per sidebar item (a dozen-plus times per page render), so an
     * uncached query here multiplies into a dozen-plus identical queries.
     */
    protected static function disabledSet(string $role): array
    {
        return once(fn () => DisabledRolePage::where('role', $role)->pluck('route')->all());
    }
}
