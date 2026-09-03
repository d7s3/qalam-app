<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $roles = ['manager', 'supervisor', 'teacher', 'guardian'];

    /**
     * The eight remaining reports.
     *
     * Granted to their roles as they are added, except the three whose subject
     * is not the student: a guardian reads about his children, and the teachers,
     * the forms and the tasks of the academy are not his to read. He can still
     * be given any of them from the permissions screen — this only sets where
     * each starts.
     *
     * @var array<string, string>
     */
    private array $reports = [
        'mutun' => 'تقرير المتون والمنظومات',
        'exams' => 'تقرير الاختبارات',
        'gamification' => 'تقرير التحفيز',
        'retention' => 'تقرير الانتظام والتسرّب',
        'family-contact' => 'تقرير التواصل مع الأسرة',
        'teacher-performance' => 'تقرير أداء المعلمين',
        'forms' => 'تقرير النماذج',
        'tasks' => 'تقرير المهام',
    ];

    /** Reports a guardian does not start with. */
    private array $notForGuardian = ['teacher-performance', 'forms', 'tasks', 'retention'];

    /** Reports a cohort teacher does not start with. */
    private array $notForTeacher = ['teacher-performance', 'forms'];

    public function up(): void
    {
        foreach ($this->roles as $key) {
            $role = Role::where('key', $key)->first();

            if (! $role) {
                continue;
            }

            $sort = Screen::where('owner_role_id', $role->id)->max('sort_order') ?? 0;

            foreach ($this->reports as $report => $label) {
                $screen = Screen::create([
                    'owner_role_id' => $role->id,
                    'group_label' => 'التقارير',
                    'route_name' => $key.'.reports.'.$report,
                    'label' => $label,
                    'sort_order' => ++$sort,
                    'is_protected' => false,
                ]);

                $withheld = ($key === 'guardian' && in_array($report, $this->notForGuardian, true))
                    || ($key === 'teacher' && in_array($report, $this->notForTeacher, true));

                if (! $withheld) {
                    $screen->permissions()->create(['role_id' => $role->id]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->roles as $role) {
            foreach (array_keys($this->reports) as $report) {
                Screen::where('route_name', $role.'.reports.'.$report)->delete();
            }
        }
    }
};
