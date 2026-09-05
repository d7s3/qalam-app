<?php

use App\Support\HijriDate;
use App\Support\HijriSeasons;
use Illuminate\Support\Carbon;

/**
 * The seasons recur by the Hijri calendar itself, so no year has to be entered
 * by hand and no year can be forgotten.
 */
it('knows Ramadan without anybody entering it', function () {
    // 1 Ramadan 1448.
    expect(HijriSeasons::isIn('ramadan', '2027-02-08'))->toBeTrue();
    expect(HijriSeasons::isIn('ramadan', '2026-09-05'))->toBeFalse();
});

it('separates the last ten from the month around them', function () {
    $parts = HijriDate::parts('2027-02-08');

    expect($parts['month'])->toBe(9);
    expect($parts['day'])->toBe(1);

    expect(HijriSeasons::isIn('last_ten', '2027-02-08'))->toBeFalse();

    // 21 Ramadan of the same year.
    expect(HijriSeasons::isIn('last_ten', '2027-02-28'))->toBeTrue();
    expect(HijriSeasons::isIn('ramadan', '2027-02-28'))->toBeTrue();
});

it('holds the ten of Dhul-Hijjah and the day of Arafah inside it', function () {
    // 9 Dhul-Hijjah 1448.
    expect(HijriSeasons::isIn('arafah', '2027-05-15'))->toBeTrue();
    expect(HijriSeasons::isIn('dhul_hijjah_ten', '2027-05-15'))->toBeTrue();

    // The eleventh is past them both.
    expect(HijriSeasons::isIn('dhul_hijjah_ten', '2027-05-17'))->toBeFalse();
});

it('finds the white days in whichever month they fall', function () {
    foreach (['2026-09-05', '2027-02-08'] as $anchor) {
        $parts = HijriDate::parts($anchor);
        $day = Carbon::parse($anchor)->addDays(14 - $parts['day']);

        expect(HijriSeasons::isIn('white_days', $day))->toBeTrue();
    }
});

it('keeps Monday and Thursday every week of the year', function () {
    // 8 February 2027 is a Monday.
    expect(HijriSeasons::isIn('mon_thu', '2027-02-08'))->toBeTrue();
    expect(HijriSeasons::isIn('mon_thu', '2027-02-11'))->toBeTrue();
    expect(HijriSeasons::isIn('mon_thu', '2027-02-09'))->toBeFalse();
});

it('gives every season a purpose, not only a name', function () {
    foreach (HijriSeasons::all() as $season) {
        expect($season['purpose'])->not->toBeEmpty();
        expect($season['label'])->not->toBeEmpty();
    }
});
