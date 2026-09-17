<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

/**
 * Register «سجلّ البرنامج الذاتي» so the academy can open and close it.
 *
 * A page nobody has registered is open to everyone — which is the only safe
 * reading for a page written before anyone has decided who may see it, and the
 * reason it must be described here: an administrator cannot close a switch that
 * does not exist.
 */
return new class extends Migration
{
    private const ROLES = [
        'student' => ['group' => 'عام', 'label' => 'سجلّ إنجازي'],
        'teacher' => ['group' => 'المتابعة', 'label' => 'سجلّ الطالب'],
        'supervisor' => ['group' => 'المتابعة والتقارير', 'label' => 'سجلّ الطالب'],
        'manager' => ['group' => 'المتابعة والتقارير', 'label' => 'سجلّ الطالب'],
    ];

    public function up(): void
    {
        foreach (self::ROLES as $key => $said) {
            $role = Role::where('key', $key)->first();

            if (! $role || Screen::where('route_name', "{$key}.self-program-record")->exists()) {
                continue;
            }

            $screen = Screen::create([
                'owner_role_id' => $role->id,
                'group_label' => $said['group'],
                'route_name' => "{$key}.self-program-record",
                'label' => $said['label'],
                'icon' => 'clipboard-document-check',
                'sort_order' => Screen::where('owner_role_id', $role->id)->max('sort_order') + 1,
            ]);

            $screen->permissions()->create(['role_id' => $role->id]);
        }
    }

    public function down(): void
    {
        Screen::whereIn(
            'route_name',
            array_map(fn ($k) => "{$k}.self-program-record", array_keys(self::ROLES)),
        )->delete();
    }
};
