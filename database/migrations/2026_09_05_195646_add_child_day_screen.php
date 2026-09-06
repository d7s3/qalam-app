<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $guardian = Role::where('key', 'guardian')->first();

        if (! $guardian || Screen::where('route_name', 'guardian.child-day')->exists()) {
            return;
        }

        $screen = Screen::create([
            'owner_role_id' => $guardian->id,
            'group_label' => 'المتابعة',
            'route_name' => 'guardian.child-day',
            'label' => 'يوم ابني',
            'icon' => 'sun',
            'sort_order' => 0,
        ]);

        $screen->permissions()->create(['role_id' => $guardian->id]);
    }

    public function down(): void
    {
        Screen::where('route_name', 'guardian.child-day')->delete();
    }
};
