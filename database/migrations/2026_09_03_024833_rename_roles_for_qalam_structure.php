<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The names جمعية قلم actually uses.
     *
     * Only the labels move. The keys stay as they are — `supervisor` is written
     * into every route name, guard and middleware string in the application, and
     * renaming it would be a rewrite rather than a renaming. What a person reads
     * is the label; what the code reads is the key, and the two need not match.
     *
     * @var array<string, string>
     */
    private array $roleLabels = [
        'manager' => 'مدير المركز',
        'supervisor' => 'مشرف دفعة',
        'teacher' => 'معلم دفعة',
        'student' => 'طالب',
        'guardian' => 'ولي الأمر',
    ];

    /**
     * The hierarchy reads the same way: a stage is a programme, a circle a cohort.
     *
     * @var array<string, string>
     */
    private array $screenLabels = [
        'manager.stages' => 'البرامج',
        'manager.circles' => 'الدفعات',
        'supervisor.circles' => 'الدفعات',
        'teacher.leaderboards' => 'مسابقات الدفعة',
    ];

    /** @var array<string, string> */
    private array $previousRoleLabels = [
        'manager' => 'المدير',
        'supervisor' => 'المشرف',
        'teacher' => 'المعلم',
        'student' => 'الطالب',
        'guardian' => 'ولي الأمر',
    ];

    /** @var array<string, string> */
    private array $previousScreenLabels = [
        'manager.stages' => 'المراحل التعليمية',
        'manager.circles' => 'الحلقات',
        'supervisor.circles' => 'الحلقات',
        'teacher.leaderboards' => 'مسابقات الحلقة',
    ];

    public function up(): void
    {
        $this->apply($this->roleLabels, $this->screenLabels);
    }

    public function down(): void
    {
        $this->apply($this->previousRoleLabels, $this->previousScreenLabels);
    }

    /**
     * @param  array<string, string>  $roles
     * @param  array<string, string>  $screens
     */
    private function apply(array $roles, array $screens): void
    {
        foreach ($roles as $key => $label) {
            Role::where('key', $key)->update(['label' => $label]);
        }

        foreach ($screens as $route => $label) {
            Screen::where('route_name', $route)->update(['label' => $label]);
        }
    }
};
