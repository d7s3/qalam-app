<?php

namespace App\Services;

use App\Models\AcademicCalendarEvent;
use App\Models\SelfProgramDayOverride;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Support\SelfProgramSheet;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Laying out a whole year of the programme at once.
 *
 * Filling fifty-two weeks by hand, five fields each, is two hundred and sixty
 * entries — enough that nobody would do it. So the year is laid out in one pass
 * and filled by whichever route suits: a blank calendar to write into, one week
 * copied across the rest, an annual total divided out, or a sheet handed over
 * whole.
 */
class SelfProgramYearBuilder
{
    /**
     * Lay out consecutive weeks from a starting date.
     *
     * A week the academy never meets in — a holiday, the gap between terms — is
     * skipped rather than created empty, so week numbers follow the weeks that
     * are actually taught. Only whole weeks already present are left alone, so
     * running this twice does not double the year.
     *
     * @return array{created: int, skipped: int}
     */
    public function generate(
        CarbonInterface $startsOn,
        int $count,
        ?int $stageId = null,
        ?int $circleId = null,
        string $programType = SelfProgramWeek::TYPE_SELF,
    ): array {
        $cursor = Carbon::parse($startsOn)->startOfDay();
        $existing = SelfProgramWeek::where('program_type', $programType)
            ->where('stage_id', $stageId)
            ->where('circle_id', $circleId);

        $number = (int) ($existing->max('week_number') ?? 0);

        // The days already spoken for, as ranges rather than start dates. A
        // week beginning on a Sunday that nothing else begins on can still run
        // straight through one that began on the Saturday before it, and
        // comparing only the first day let exactly that be created.
        $taken = $existing->get(['starts_on', 'ends_on'])
            ->map(fn (SelfProgramWeek $week) => [
                Carbon::parse($week->starts_on)->toDateString(),
                Carbon::parse($week->ends_on)->toDateString(),
            ])
            ->all();

        $created = 0;
        $skipped = 0;

        for ($i = 0; $i < $count; $i++) {
            $ends = $cursor->copy()->addDays(6);

            $isTaught = AcademicCalendarEvent::workingDaysBetween($cursor, $ends, $stageId) !== [];
            $from = $cursor->toDateString();
            $to = $ends->toDateString();
            $isNew = ! collect($taken)->contains(fn (array $range) => $range[0] <= $to && $range[1] >= $from);

            if ($isTaught && $isNew) {
                SelfProgramWeek::create([
                    'stage_id' => $stageId,
                    'circle_id' => $circleId,
                    'program_type' => $programType,
                    'week_number' => ++$number,
                    'starts_on' => $cursor->copy(),
                    'ends_on' => $ends,
                ])->ensureAllTracks();

                $taken[] = [$cursor->toDateString(), $ends->toDateString()];
                $created++;
            } elseif (! $isTaught) {
                $skipped++;
            }

            $cursor = $cursor->addDays(7);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Write one week's five fields onto every other week in a set.
     *
     * The source keeps its own content; only the others are overwritten, and a
     * supervisor then edits the few weeks that differ.
     */
    public function copyAcross(SelfProgramWeek $source, Collection $targets): int
    {
        $source->loadMissing('items');
        $written = 0;

        // The day plan is written against real dates, so it is carried by the
        // offset between the two weeks rather than copied verbatim: the
        // source's Sunday becomes the target's Sunday.
        $sourceDays = SelfProgramDayOverride::whereIn('self_program_item_id', $source->items->pluck('id'))
            ->get()
            ->groupBy('self_program_item_id');

        foreach ($targets as $target) {
            if ($target->id === $source->id) {
                continue;
            }

            $shift = $source->starts_on->diffInDays($target->starts_on, false);

            foreach ($source->items as $item) {
                $copy = $target->items()->updateOrCreate(
                    ['track' => $item->track->value],
                    [
                        'description' => $item->description,
                        // Carried too: a week copied without its link is a week
                        // whose content the student cannot reach.
                        'content_url' => $item->content_url,
                        'content_label' => $item->content_label,
                        'target_amount' => $item->target_amount,
                        'unit' => $item->unit,
                    ],
                );

                foreach ($sourceDays[$item->id] ?? [] as $day) {
                    $on = $day->day_date->copy()->addDays($shift);

                    SelfProgramDayOverride::updateOrCreate(
                        [
                            'self_program_item_id' => $copy->id,
                            'day_date' => $on,
                            'circle_id' => $day->circle_id,
                            'student_id' => $day->student_id,
                        ],
                        ['content' => $day->content, 'amount' => $day->amount],
                    );
                }
            }

            // And the days the week joins into one column travel with it.
            $target->update([
                'merged_days' => collect($source->merged_days ?? [])
                    ->map(fn ($group) => collect($group)
                        ->map(fn ($day) => Carbon::parse($day)->addDays($shift)->format('Y-m-d'))
                        ->all())
                    ->all(),
            ]);

            $written++;
        }

        return $written;
    }

    /**
     * Divide a year's total for each field evenly across a set of weeks.
     *
     * The remainder from the division lands on the last week rather than being
     * dropped, so the weeks add back up to the total the supervisor entered.
     *
     * @param  array<string, float>  $annualByTrack  keyed by track value
     */
    public function distribute(array $annualByTrack, Collection $weeks): void
    {
        $weeks = $weeks->sortBy('week_number')->values();

        if ($weeks->isEmpty()) {
            return;
        }

        foreach ($annualByTrack as $trackValue => $annual) {
            $track = SelfProgramTrack::findByKey((string) $trackValue);
            $annual = (float) $annual;

            if (! $track || $annual <= 0) {
                continue;
            }

            $perWeek = round($annual / $weeks->count(), 2);
            $allocated = 0.0;

            foreach ($weeks as $index => $week) {
                $isLast = $index === $weeks->count() - 1;
                $amount = $isLast ? round($annual - $allocated, 2) : $perWeek;
                $allocated += $amount;

                // Only the number is being redistributed: a unit the supervisor
                // chose says what his amounts mean, and overwriting it would
                // quietly change the reading on every student's card.
                $item = $week->items()->firstOrNew(['track' => $track->value]);
                $item->target_amount = max(0, $amount);
                $item->unit = $track->fixedUnit() ?? ($item->unit ?: $track->defaultUnit());
                $item->save();
            }
        }
    }

    /**
     * Read a handed-over sheet onto the weeks already laid out.
     *
     * A row naming a week that does not exist is reported rather than silently
     * creating one out of sequence: the calendar decides which weeks are taught,
     * and a sheet should not overrule it.
     *
     * @return array{written: int, errors: array<int, string>}
     */
    public function import(
        string $path,
        ?int $stageId = null,
        ?int $circleId = null,
        string $programType = SelfProgramWeek::TYPE_SELF,
        ?string $extension = null,
    ): array {
        $sheet = SelfProgramSheet::read($path, $extension);

        $weeks = SelfProgramWeek::where('program_type', $programType)
            ->where('stage_id', $stageId)
            ->where('circle_id', $circleId)
            ->get()
            ->keyBy('week_number');

        $written = 0;
        $errors = $sheet['errors'];

        foreach ($sheet['rows'] as $row) {
            $week = $weeks->get($row['week']);

            if (! $week) {
                $errors[] = "السطر {$row['line']}: لا يوجد أسبوع رقم {$row['week']}؛ ولّد الأسابيع أولاً.";

                continue;
            }

            $week->items()->updateOrCreate(
                ['track' => $row['track']->value],
                [
                    'description' => $row['description'],
                    'target_amount' => max(0, $row['amount']),
                    'unit' => $row['track']->fixedUnit() ?? ($row['unit'] ?: $row['track']->defaultUnit()),
                ],
            );

            $written++;
        }

        return ['written' => $written, 'errors' => $errors];
    }
}
