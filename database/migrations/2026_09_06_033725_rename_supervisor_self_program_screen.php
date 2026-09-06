<?php

use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One name for one screen, in every office.
     *
     * The held navigation offers a page from a junior office unless the reader
     * already owns one by the same name under his own prefix. The supervisor's
     * was `self-program` while the two new ones were `self-program-weeks`, so
     * he was offered the teacher's copy of the screen he already had.
     */
    public function up(): void
    {
        Screen::where('route_name', 'supervisor.self-program')
            ->update(['route_name' => 'supervisor.self-program-weeks']);

        // A screen says how it renders when its name does not: all three now
        // share one page, and none of them has a view of its own name.
        Screen::whereIn('route_name', [
            'manager.self-program-weeks',
            'supervisor.self-program-weeks',
            'teacher.self-program-weeks',
        ])->update(['view' => 'shared.self-program-weeks']);
    }

    public function down(): void
    {
        Screen::where('route_name', 'supervisor.self-program-weeks')
            ->update(['route_name' => 'supervisor.self-program']);
    }
};
