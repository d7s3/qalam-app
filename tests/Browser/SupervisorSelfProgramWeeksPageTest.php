<?php

use App\Models\AiProviderCredential;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Support\AiSettings;

it('opens the supervisor on this week, plainly, with the rest folded away', function () {
    $stage = Stage::factory()->create(['name' => 'تأهيل']);
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($stage->id);

    foreach ([-7, 0, 7] as $shift) {
        $starts = now('Asia/Riyadh')->startOfWeek(0)->addDays($shift);

        SelfProgramWeek::create([
            'stage_id' => $stage->id,
            'program_type' => SelfProgramWeek::TYPE_SELF,
            'week_number' => 7 + intdiv($shift, 7),
            'starts_on' => $starts->toDateString(),
            'ends_on' => $starts->copy()->addDays(6)->toDateString(),
        ]);
    }

    AiProviderCredential::create(['provider' => AiSettings::provider(), 'api_key' => 'test-key-not-real']);

    $this->actingAs($supervisor, 'supervisor');

    visit('/supervisor/self-program-weeks')
        ->assertNoJavaScriptErrors()
        ->assertSee('الأسبوع 7')
        ->assertSee('هذا الأسبوع')
        ->assertSee('الصق رسالة الأسبوع')
        ->assertDontSee('إعداد السنة كاملة')
        ->screenshot(filename: 'supervisor-self-program-weeks');
});
