<?php

namespace App\Support;

/**
 * What a field of the self programme is measured in, and how that measure
 * behaves.
 *
 * The programme was written with one number and a free-text unit beside it, and
 * that let a supervisor ask for «٢٫٥ حديث» or a student report his listening in
 * a unit nobody had defined. Neither is a mistake anybody makes on purpose; both
 * are mistakes the form invited.
 *
 * So the units are a closed vocabulary, and each one says what it counts. Pages,
 * hadiths and verses are counted whole — half a hadith is not a quantity. Time
 * is not counted at all: it is asked for in hours and minutes, and kept in
 * minutes so that arithmetic across a term still adds up.
 *
 * The vocabulary is small on purpose. A unit the academy adds later is treated
 * as a plain count, which is the safe reading — it will not silently become a
 * duration.
 */
class SelfProgramUnit
{
    public const PAGE = 'صفحة';

    public const MINUTE = 'دقيقة';

    public const HADITH = 'حديث';

    public const VERSE = 'بيت';

    /**
     * The vocabulary: how each unit is counted, and how it is said of two and
     * of a few — because «٢ صفحة» is not Arabic and a programme is read aloud.
     *
     * @var array<string, array{duration: bool, two: string, few: string}>
     */
    private const KNOWN = [
        self::PAGE => ['duration' => false, 'two' => 'صفحتان', 'few' => 'صفحات'],
        self::HADITH => ['duration' => false, 'two' => 'حديثان', 'few' => 'أحاديث'],
        self::VERSE => ['duration' => false, 'two' => 'بيتان', 'few' => 'أبيات'],
        self::MINUTE => ['duration' => true, 'two' => 'دقيقتان', 'few' => 'دقائق'],
    ];

    /** Hours, said the same way. */
    private const HOUR = ['one' => 'ساعة', 'two' => 'ساعتان', 'few' => 'ساعات'];

    /** Time is asked for in hours and minutes; everything else in whole things. */
    public static function isDuration(?string $unit): bool
    {
        return (bool) (self::KNOWN[$unit ?? '']['duration'] ?? false);
    }

    /**
     * Whether a unit may be halved.
     *
     * Only the mushaf page may: half a page is a real amount, and the recitation
     * bridge writes them.
     */
    public static function allowsFractions(?string $unit): bool
    {
        return $unit === self::PAGE;
    }

    /** The smallest step the form should offer. */
    public static function step(?string $unit): float
    {
        return self::allowsFractions($unit) ? 0.5 : 1;
    }

    /**
     * Round an amount to something the unit can actually hold.
     *
     * A count is made whole rather than refused, because the number is nearly
     * always right and only its precision is wrong.
     */
    public static function normalise(float $amount, ?string $unit): float
    {
        if (self::allowsFractions($unit)) {
            return round($amount * 2) / 2;
        }

        return (float) round($amount);
    }

    /** The vocabulary itself, for a form that offers a choice. */
    public static function all(): array
    {
        return array_keys(self::KNOWN);
    }

    /** Whether a unit is one the vocabulary knows how to count. */
    public static function isKnown(?string $unit): bool
    {
        return isset(self::KNOWN[$unit ?? '']);
    }

    /**
     * The plural a field is labelled by — «صفحات», «أحاديث» — which is how the
     * five name their own units, so a sixth reads like them.
     */
    public static function plural(string $unit): string
    {
        return self::KNOWN[$unit]['few'] ?? $unit;
    }

    /**
     * Say an amount in its unit, the way it would be read aloud.
     *
     * Duration is said in hours and minutes however many minutes it holds, so
     * «٩٠ دقيقة» is written «ساعة ونصف»' worth: ساعة و٣٠ دقيقة.
     */
    public static function say(float $amount, ?string $unit): string
    {
        if ($unit === null || $unit === '') {
            return self::number($amount);
        }

        if (! self::isDuration($unit)) {
            return self::counted($amount, $unit, self::KNOWN[$unit] ?? null);
        }

        $minutes = (int) round($amount);

        if ($minutes < 60) {
            return self::counted($minutes, self::MINUTE, self::KNOWN[self::MINUTE]);
        }

        $hours = self::counted(intdiv($minutes, 60), 'ساعة', self::HOUR);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours : $hours.' و'.self::counted($rest, self::MINUTE, self::KNOWN[self::MINUTE]);
    }

    /** Split minutes into the two boxes a form asks them in. */
    public static function toHoursAndMinutes(float $minutes): array
    {
        $whole = (int) round($minutes);

        return ['hours' => intdiv($whole, 60), 'minutes' => $whole % 60];
    }

    /** And back again. */
    public static function fromHoursAndMinutes(mixed $hours, mixed $minutes): float
    {
        return (float) max(0, ((int) $hours * 60) + (int) $minutes);
    }

    /**
     * A number without the trailing zeros a decimal column carries.
     *
     * The programme is full of whole amounts held in a decimal column, and
     * «٣٫٠٠ صفحات» reads like a machine wrote it.
     */
    public static function number(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Arabic counts its things by how many there are: one is named alone, two
     * has its own form, three to ten take the plural, and eleven upwards go back
     * to the singular.
     *
     * @param  array{two: string, few: string}|null  $forms
     */
    private static function counted(float $amount, string $unit, ?array $forms): string
    {
        $forms ??= ['two' => $unit, 'few' => $unit];

        if (fmod($amount, 1.0) !== 0.0) {
            return self::number($amount).' '.$unit;
        }

        $whole = (int) $amount;

        return match (true) {
            $whole === 1 => $forms['one'] ?? $unit,
            $whole === 2 => $forms['two'],
            $whole >= 3 && $whole <= 10 => $whole.' '.$forms['few'],
            default => $whole.' '.$unit,
        };
    }
}
