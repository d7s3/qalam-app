<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The reports catalogue, as screens.
     *
     * Each report is a screen of its own — `<role>.reports.<key>` — so it is
     * granted, withheld, or opened for one person alone from the same screen
     * that governs every other page. The `<role>.reports` page above them is
     * only the door: what a reader finds behind it is whichever of the three
     * he holds.
     *
     * @var array<int, string>
     */
    private array $roles = ['manager', 'supervisor', 'teacher', 'guardian'];

    /** @var array<string, string> */
    private array $reports = [
        'attendance' => 'تقرير الحضور والانضباط',
        'memorization' => 'تقرير الحفظ والمراجعة',
        'self-program' => 'تقرير البرنامج الذاتي',
    ];

    public function up(): void
    {
        foreach ($this->roles as $key) {
            $role = Role::where('key', $key)->first();

            if (! $role) {
                continue;
            }

            $sort = Screen::where('owner_role_id', $role->id)->max('sort_order') ?? 0;

            $door = Screen::create([
                'owner_role_id' => $role->id,
                'group_label' => 'التقارير',
                'route_name' => $key.'.reports',
                'label' => 'التقارير',
                'sort_order' => ++$sort,
                'is_protected' => false,
            ]);
            $door->permissions()->create(['role_id' => $role->id]);

            foreach ($this->reports as $report => $label) {
                $screen = Screen::create([
                    'owner_role_id' => $role->id,
                    'group_label' => 'التقارير',
                    'route_name' => $key.'.reports.'.$report,
                    'label' => $label,
                    'sort_order' => ++$sort,
                    'is_protected' => false,
                ]);

                // A guardian reads about his own children only, which the reach
                // already limits; the rest are granted to their roles as well.
                $screen->permissions()->create(['role_id' => $role->id]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->roles as $role) {
            Screen::where('route_name', $role.'.reports')
                ->orWhere('route_name', 'like', $role.'.reports.%')
                ->delete();
        }
    }
};
