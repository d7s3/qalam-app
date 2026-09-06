<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The seasons of the Hijri year, as rules rather than as rows.
 *
 * Ramadan, the ten of Dhul-Hijjah, the white days — these recur by the Hijri
 * calendar itself, and entering them by hand every year is both work and a
 * thing to forget. Here they are the rule they have always been, and any year
 * answers without anybody typing a date.
 *
 * Each carries a purpose rather than only a colour, because the point of a
 * season in a school of this kind is what the circle turns its attention to
 * while it lasts.
 *
 * The rules are fixed; what the academy decides to do in a season is written
 * per programme and lives elsewhere — this is the rhythm, not the content.
 */
class HijriSeasons
{
    /**
     * Every season, in the order a year meets them.
     *
     * @return array<int, array{key: string, label: string, purpose: string, months: array<int, int>, days: array<int, int>|null, weekdays: array<int, int>|null}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'muharram',
                'label' => 'شهر الله المحرّم',
                'purpose' => 'شهر حرام، وفيه عاشوراء. يُستحبّ فيه الصيام والإكثار من العمل الصالح.',
                'months' => [1],
                'days' => null,
                'weekdays' => null,
            ],
            [
                'key' => 'ashura',
                'label' => 'يوم عاشوراء',
                'purpose' => 'صيامه يكفّر السنة الماضية، ويُستحبّ صوم التاسع معه.',
                'months' => [1],
                'days' => [9, 10],
                'weekdays' => null,
            ],
            [
                'key' => 'rajab',
                'label' => 'شهر رجب',
                'purpose' => 'من الأشهر الحرم؛ يُعظَّم فيه اجتناب الظلم والذنب.',
                'months' => [7],
                'days' => null,
                'weekdays' => null,
            ],
            [
                'key' => 'shaban',
                'label' => 'شهر شعبان',
                'purpose' => 'شهر تُرفع فيه الأعمال، وكان النبيّ ﷺ يُكثر الصيام فيه.',
                'months' => [8],
                'days' => null,
                'weekdays' => null,
            ],
            [
                'key' => 'ramadan',
                'label' => 'رمضان',
                'purpose' => 'شهر القرآن: تُضاعف فيه الأوراد، ويُشدّ فيه الحفظ والمراجعة.',
                'months' => [9],
                'days' => null,
                'weekdays' => null,
            ],
            [
                'key' => 'last_ten',
                'label' => 'العشر الأواخر من رمضان',
                'purpose' => 'فيها ليلة القدر؛ يُشمَّر فيها عن الاجتهاد في القيام والتلاوة.',
                'months' => [9],
                'days' => [21, 22, 23, 24, 25, 26, 27, 28, 29, 30],
                'weekdays' => null,
            ],
            [
                'key' => 'dhul_hijjah_ten',
                'label' => 'العشر من ذي الحجة',
                'purpose' => 'أفضل أيام الدنيا: يُكثَر فيها التكبير والذكر والعمل الصالح.',
                'months' => [12],
                'days' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                'weekdays' => null,
            ],
            [
                'key' => 'arafah',
                'label' => 'يوم عرفة',
                'purpose' => 'صيامه لغير الحاجّ يكفّر سنتين، وهو يوم دعاء.',
                'months' => [12],
                'days' => [9],
                'weekdays' => null,
            ],
            [
                'key' => 'white_days',
                'label' => 'الأيام البيض',
                'purpose' => 'صيام ثلاثة أيام من كل شهر، وهي كصيام الدهر.',
                'months' => [],
                'days' => [13, 14, 15],
                'weekdays' => null,
            ],
            [
                'key' => 'mon_thu',
                'label' => 'الاثنين والخميس',
                'purpose' => 'يومان تُعرض فيهما الأعمال، وكان النبيّ ﷺ يتحرّى صيامهما.',
                'months' => [],
                'days' => null,
                // 2 = Monday, 5 = Thursday in Carbon's dayOfWeek + 1.
                'weekdays' => [2, 5],
            ],
        ];
    }

    /**
     * The seasons a date falls inside.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function on(Carbon|string $date): array
    {
        $day = Carbon::parse($date);
        $hijri = HijriDate::parts($day);

        return array_values(array_filter(
            self::all(),
            fn (array $season) => self::covers($season, $hijri, $day),
        ));
    }

    /**
     * Whether a season has begun and not yet ended on a date — used to say
     * "we are in Ramadan" rather than "today is a day of Ramadan".
     */
    public static function isIn(string $key, Carbon|string $date): bool
    {
        return collect(self::on($date))->contains(fn (array $season) => $season['key'] === $key);
    }

    /** @param  array{year: int, month: int, day: int}  $hijri */
    private static function covers(array $season, array $hijri, Carbon $day): bool
    {
        if ($season['weekdays'] !== null) {
            return in_array($day->dayOfWeek + 1, $season['weekdays'], true);
        }

        // An empty month list means every month of the year.
        if ($season['months'] !== [] && ! in_array($hijri['month'], $season['months'], true)) {
            return false;
        }

        return $season['days'] === null || in_array($hijri['day'], $season['days'], true);
    }
}
