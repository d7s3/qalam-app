<?php

use App\Models\Screen;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The screen labels the vocabulary change could not reach.
     *
     * A cohort is a دفعة; only one whose content is Quranic is a حلقة. The
     * views were renamed with the code, but a screen's label lives in a row.
     */
    public function up(): void
    {
        Screen::where('label', 'متابعة الحلقات السنوي')
            ->update(['label' => 'متابعة الدفعات السنوي']);
    }

    public function down(): void
    {
        Screen::where('label', 'متابعة الدفعات السنوي')
            ->update(['label' => 'متابعة الحلقات السنوي']);
    }
};
