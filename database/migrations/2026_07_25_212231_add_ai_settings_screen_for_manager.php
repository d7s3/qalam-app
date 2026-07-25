<?php

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
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
            'route_name' => 'manager.ai-settings',
            'label' => 'إعدادات الذكاء الاصطناعي',
            'icon' => 'sparkles',
            'sort_order' => Screen::where('owner_role_id', $manager->id)->max('sort_order') + 1,
            'is_protected' => false,
        ]);

        $screen->permissions()->create(['role_id' => $manager->id]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Screen::where('route_name', 'manager.ai-settings')->delete();
    }
};
