<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $manager = Role::where('key', 'manager')->first();

        if (! $manager) {
            return;
        }

        $screen = Screen::create([
            'owner_role_id' => $manager->id,
            'group_label' => 'إدارة النظام',
            'route_name' => 'manager.stage-access',
            'label' => 'صلاحيات البرامج',
            'sort_order' => Screen::where('owner_role_id', $manager->id)->max('sort_order') + 1,
            // Like the two screens beside it: the way back in if a grant goes
            // wrong, so it can never be switched off from inside itself.
            'is_protected' => true,
        ]);

        $screen->permissions()->create(['role_id' => $manager->id]);
    }

    public function down(): void
    {
        Screen::where('route_name', 'manager.stage-access')->delete();
    }
};
