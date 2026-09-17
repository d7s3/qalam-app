<?php

namespace App\Support;

/**
 * How a cohort runs its Quran.
 *
 * `circles.is_quranic` was answering two questions with one switch: whether the
 * cohort recites at all, and whether it keeps a day-by-day plan of ayah ranges.
 * They are not the same question. A حلقة that hears its students but does not
 * want a written plan for every day had nowhere to stand: turning the switch off
 * took the recitation with it, and leaving it on demanded a plan nobody wrote.
 *
 * So the switch is split into three answers, and the old one is derived from
 * this rather than the other way round — «يقرأ» is «ليس none».
 */
enum QuranMode: string
{
    /** No Quran at all: a دفعة, whose Quran wird is simply not among its fields. */
    case None = 'none';

    /**
     * The whole of it: a plan of ayah ranges, a grade for each day's hifz and
     * review, and a wird written from those grades by the bridge.
     */
    case Detailed = 'detailed';

    /**
     * The sum of it: no plan and no per-day grade — a page count for the day,
     * written by the student or by his teacher, whichever of them is at hand.
     */
    case Summary = 'summary';

    public function label(): string
    {
        return match ($this) {
            self::None => 'بلا برنامج قرآني',
            self::Detailed => 'قرآني تفصيلي',
            self::Summary => 'قرآني إجمالي',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'دفعةٌ لا ورد قرآني فيها؛ يبقى لها البرنامج الذاتي وحده.',
            self::Detailed => 'خطةٌ بالآيات يوماً بيوم، وتقييمُ حفظٍ ومراجعة، ويُكتب الورد من التسميع تلقائياً.',
            self::Summary => 'بلا خطةٍ مفصّلة: عددُ صفحات اليوم فقط، يسجّله الطالب أو معلّمه.',
        };
    }

    /** Whether this cohort recites at all — what `is_quranic` used to mean. */
    public function recites(): bool
    {
        return $this !== self::None;
    }

    /** Whether it keeps the day-by-day plan, and the screens that go with it. */
    public function isDetailed(): bool
    {
        return $this === self::Detailed;
    }

    /** @return array<int, self> */
    public static function ordered(): array
    {
        return [self::Detailed, self::Summary, self::None];
    }
}
