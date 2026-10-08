<?php

use App\Models\Teacher;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * A browser test that asks for something the page does not have has to fail
 * and say so, within the timeout, rather than wait for ever. Playwright reads
 * an action's timeout from the message's metadata since 1.62, where Pest's
 * browser plugin 4.x does not put it, so on a newer Playwright every such
 * action hung the run without a word. Playwright is held at 1.61 until Pest 5.
 */
it('fails a click on what the page does not have, rather than waiting for ever', function () {
    $this->actingAs(Teacher::factory()->create(['is_approved' => true]), 'teacher');

    $page = visit('/teacher/students');
    $started = microtime(true);

    expect(fn () => $page->click('#nothing-by-this-id'))->toThrow(ExpectationFailedException::class)
        ->and(microtime(true) - $started)->toBeLessThan(15);
});
