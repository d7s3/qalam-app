<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One screen, four offices.
     *
     * The day is the same question for everyone who has one — what am I
     * expected at, what is assigned to me, what did I miss — and each office
     * gets its own registered screen so the administrator can grant or withhold
     * it per role like any other.
     */
    private const ROLES = ['manager', 'supervisor', 'teacher', 'student'];

    public function up(): void
    {
        foreach (self::ROLES as $key) {
            $role = Role::where('key', $key)->first();

            if (! $role || Screen::where('route_name', "{$key}.my-day")->exists()) {
                continue;
            }

            $screen = Screen::create([
                'owner_role_id' => $role->id,
                'group_label' => $key === 'student' ? 'البرنامج' : 'المتابعة',
                'route_name' => "{$key}.my-day",
                'label' => 'يومي',
                'icon' => 'sun',
                'sort_order' => 0,
            ]);

            $screen->permissions()->create(['role_id' => $role->id]);
        }
    }

    public function down(): void
    {
        Screen::whereIn('route_name', array_map(fn ($key) => "{$key}.my-day", self::ROLES))->delete();
    }
};
