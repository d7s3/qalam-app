<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The screen that makes managers.
     *
     * Supervisors and teachers could be created from the manager's area since
     * the beginning; managers could not, so the three tiers of the office
     * existed and nobody could be made into one. Registering the page puts it
     * inside the permission layer like every other, and `ManagerTier` keeps it
     * with the centre — a manager over one programme does not make managers.
     */
    public function up(): void
    {
        $manager = Role::where('key', 'manager')->first();

        if (! $manager) {
            return;
        }

        $screen = Screen::create([
            'owner_role_id' => $manager->id,
            'group_label' => 'إدارة النظام',
            'route_name' => 'manager.managers',
            'label' => 'المديرون',
            'sort_order' => Screen::where('owner_role_id', $manager->id)->max('sort_order') + 1,
            'is_protected' => false,
        ]);

        $screen->permissions()->create(['role_id' => $manager->id]);
    }

    public function down(): void
    {
        Screen::where('route_name', 'manager.managers')->delete();
    }
};
