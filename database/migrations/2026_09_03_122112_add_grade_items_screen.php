<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A tab of the teacher's shell that nobody had registered.
     *
     * It has a route and it renders, but no screen — so no permission governed
     * it and no administrator could see it to withhold it. The other five tabs
     * of the same shell were all registered; this one was simply missed.
     */
    public function up(): void
    {
        $role = Role::where('key', 'teacher')->first();

        if (! $role || Screen::where('route_name', 'teacher.grade-items')->exists()) {
            return;
        }

        $screen = Screen::create([
            'owner_role_id' => $role->id,
            'group_label' => 'التحفيز والمنافسة',
            'route_name' => 'teacher.grade-items',
            'label' => 'بنود التقييم',
            'sort_order' => (Screen::where('owner_role_id', $role->id)->max('sort_order') ?? 0) + 1,
            'is_protected' => false,
            'view' => 'teacher.app-shell',
            'view_data' => ['initialTab' => 'grade-items'],
        ]);

        $screen->permissions()->create(['role_id' => $role->id]);
    }

    public function down(): void
    {
        Screen::where('route_name', 'teacher.grade-items')->delete();
    }
};
