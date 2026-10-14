<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;

/**
 * The plan wizard's direction step asks only what the chosen track has: a
 * review-only plan is not asked which way to memorise, nor a memorising-only
 * one which way to review.
 */
beforeEach(function () {
    $this->actingAs(Student::factory()->create([
        'circle_id' => Circle::factory()->create(['stage_id' => Stage::factory()->create()->id])->id,
        'status' => 'active',
        'is_data_completed' => true,
    ]), 'student');
});

it('asks a track only the directions it has', function (string $track, bool $hifz, bool $review) {
    visit('/student/plan/create')
        ->assertNoJavaScriptErrors()
        ->click("[x-show=\"wizardStep == 2\"] button:has-text(\"{$track}\")")
        ->click('button:has-text("التالي")')
        ->assertSee('السابق')
        ->assertScript(<<<'JS'
            (() => {
                const shown = (which) => { const el = document.querySelector(`[data-direction="${which}"]`); return !!el && el.offsetParent !== null; };
                return [shown('hifz'), shown('review')];
            })()
            JS, [$hifz, $review]);
})->with([
    'review only' => ['مراجعة فقط', false, true],
    'memorising only' => ['حفظ فقط', true, false],
    'both' => ['حفظ ومراجعة', true, true],
]);
