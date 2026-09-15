<?php

use Carbon\CarbonImmutable;

/**
 * The one place a date is written as prose.
 *
 * Everything else on screen is Arabic; `diffForHumans()` was answering in
 * English because `app.locale` is `en` and Carbon takes its language from
 * there. A guardian opening his notice board read «8 hours ago» under an
 * Arabic headline, four notices in a row.
 */
it('writes how long ago something was in Arabic', function () {
    $said = now()->subHours(8)->diffForHumans();

    expect(str_contains($said, 'ago'))->toBeFalse("«{$said}» — التاريخ ما زال بالإنجليزية");
    expect($said)->toMatch('/\p{Arabic}/u');
});

it('keeps the Arabic when the date is an immutable one, which is what the app uses', function () {
    $said = CarbonImmutable::now()->subDays(3)->diffForHumans();

    expect(str_contains($said, 'ago'))->toBeFalse("«{$said}» — التاريخ ما زال بالإنجليزية");
});
