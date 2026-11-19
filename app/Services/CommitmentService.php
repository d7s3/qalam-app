<?php

namespace App\Services;

use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Student;
use App\Models\StudentPlanDay;
use App\Models\StudentSelfProgramEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How closely students keep to their Quran plan and their self programme.
 *
 * Asked for by Faisal, 17 Nov 2026: one bar for each, for every student and
 * every group, over a period the viewer chooses, with what is left owing.
 */
class CommitmentService
{
    /** Below this in a week, his teacher is asked to act. */
    public const THRESHOLD = 70.0;

    /** The periods a viewer can choose, by key. */
    public const PERIODS = [
        'week' => 'آخر أسبوع',
        'two_weeks' => 'آخر أسبوعين',
        'month' => 'آخر شهر',
        'program' => 'من بداية البرنامج',
        'custom' => 'فترة أخرى',
    ];

    public function __construct(private SelfProgramService $selfProgram) {}

    /**
     * The days a period covers, ending yesterday: today is still his to do.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(string $period, ?string $from = null, ?string $to = null, ?CarbonInterface $today = null): array
    {
        $yesterday = Carbon::parse($today ?? Carbon::today())->startOfDay()->subDay();

        return match ($period) {
            'two_weeks' => [$yesterday->copy()->subDays(13), $yesterday],
            'month' => [$yesterday->copy()->subMonthNoOverflow()->addDay(), $yesterday],
            'program' => [Carbon::parse(SelfProgramWeek::self()->min('starts_on') ?? $yesterday->copy()->subYear())->startOfDay(), $yesterday],
            'custom' => [
                Carbon::parse($from ?: $yesterday->copy()->subDays(6))->startOfDay(),
                Carbon::parse($to ?: $yesterday)->startOfDay()->min($yesterday),
            ],
            default => [$yesterday->copy()->subDays(6), $yesterday],
        };
    }

    /**
     * The last full week, Sunday to Saturday, before today.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function lastWeek(?CarbonInterface $today = null): array
    {
        $saturday = Carbon::parse($today ?? Carbon::today())->startOfDay()->subDay();

        while (! $saturday->isSaturday()) {
            $saturday->subDay();
        }

        return [$saturday->copy()->subDays(6), $saturday];
    }

    /**
     * Each student's two bars over a period.
     *
     * The plan counts each day's memorisation and review as one part each, due
     * when its date falls in the period, done once it is graded. The programme
     * counts the weeks that ended in the period, without its Quran wird — that
     * comes from the plan and is counted there.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array{
     *     plan: array{due: int, done: int, owed: int, percent: float}|null,
     *     self: array{weeks: int, owed: int, percent: float}|null
     * }>
     */
    public function forStudents(Collection $students, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        $out = [];
        $ids = $students->pluck('id');

        foreach ($students as $student) {
            $out[$student->id] = ['plan' => null, 'self' => null];
        }

        $this->measurePlans($ids, $from, $to, $out);
        $this->measureSelfProgram($students, $from, $to, $out);

        return $out;
    }

    /**
     * A group's bars: the mean of its students' who had something asked of
     * them, and everything they owe between them.
     *
     * @param  array<int, array{plan: array<string, mixed>|null, self: array<string, mixed>|null}>  $rows
     * @return array{plan: float|null, self: float|null, plan_owed: int, self_owed: int, behind: int, students: int}
     */
    public static function summarise(array $rows): array
    {
        $plans = collect($rows)->pluck('plan')->filter();
        $selves = collect($rows)->pluck('self')->filter();

        return [
            'plan' => $plans->isEmpty() ? null : round($plans->avg('percent'), 1),
            'self' => $selves->isEmpty() ? null : round($selves->avg('percent'), 1),
            'plan_owed' => (int) $plans->sum('owed'),
            'self_owed' => (int) $selves->sum('owed'),
            'behind' => collect($rows)->filter(fn (array $row) => self::isBehind($row))->count(),
            'students' => count($rows),
        ];
    }

    /**
     * @param  array{plan: array<string, mixed>|null, self: array<string, mixed>|null}  $row
     */
    public static function isBehind(array $row): bool
    {
        return ($row['plan'] && $row['plan']['percent'] < self::THRESHOLD)
            || ($row['self'] && $row['self']['percent'] < self::THRESHOLD);
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  array<int, array<string, mixed>>  $out
     */
    private function measurePlans(Collection $ids, CarbonInterface $from, CarbonInterface $to, array &$out): void
    {
        StudentPlanDay::query()
            ->join('student_plans', 'student_plans.id', '=', 'student_plan_days.student_plan_id')
            ->whereIn('student_plans.student_id', $ids)
            ->where('student_plans.status', 'active')
            ->where('student_plans.is_approved', true)
            // whereDate: the date cast stores «Y-m-d H:i:s», so a bare between drops the last day.
            ->whereDate('student_plan_days.date', '>=', $from->toDateString())
            ->whereDate('student_plan_days.date', '<=', $to->toDateString())
            ->selectRaw('student_plans.student_id as student_id,
                sum(case when student_plan_days.from_ayah_id is not null then 1 else 0 end)
                    + sum(case when student_plan_days.review_from_ayah_id is not null then 1 else 0 end) as due,
                sum(case when student_plan_days.from_ayah_id is not null and student_plan_days.hifz_achievement is not null then 1 else 0 end)
                    + sum(case when student_plan_days.review_from_ayah_id is not null and student_plan_days.review_achievement is not null then 1 else 0 end) as done')
            ->groupBy('student_plans.student_id')
            ->get()
            ->each(function ($row) use (&$out) {
                $due = (int) $row->due;
                $done = (int) $row->done;

                if ($due > 0) {
                    $out[$row->student_id]['plan'] = [
                        'due' => $due,
                        'done' => $done,
                        'owed' => $due - $done,
                        'percent' => round($done / $due * 100, 1),
                    ];
                }
            });
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  array<int, array<string, mixed>>  $out
     */
    private function measureSelfProgram(Collection $students, CarbonInterface $from, CarbonInterface $to, array &$out): void
    {
        $students->each->loadMissing('circle');

        $stageIds = $students->map(fn (Student $s) => $s->effective_stage_id)->filter()->unique();
        $circleIds = $students->pluck('circle_id')->filter()->unique();

        // A cohort's own weeks are its programme's, written for it alone.
        $weeks = SelfProgramWeek::self()
            ->where(fn ($q) => $q->whereIn('circle_id', $circleIds)
                ->orWhere(fn ($w) => $w->whereNull('circle_id')->whereIn('stage_id', $stageIds)))
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->whereDate('ends_on', '<=', $to->toDateString())
            ->with(['items' => fn ($q) => $q->where('track', '!=', SelfProgramTrack::QURAN_WIRD)])
            ->get();

        if ($weeks->isEmpty()) {
            return;
        }

        $totals = [];

        StudentSelfProgramEntry::whereIn('student_id', $students->pluck('id'))
            ->whereIn('self_program_item_id', $weeks->pluck('items')->flatten()->pluck('id'))
            ->selectRaw('student_id, self_program_item_id, sum(amount_done) as total')
            ->groupBy('student_id', 'self_program_item_id')
            ->get()
            ->each(function ($row) use (&$totals) {
                $totals[$row->student_id][$row->self_program_item_id] = (float) $row->total;
            });

        foreach ($students as $student) {
            $own = $weeks->where('circle_id', $student->circle_id)->whereNotNull('circle_id');
            $mine = $own->isNotEmpty() ? $own : $weeks->whereNull('circle_id')->where('stage_id', $student->effective_stage_id);

            $percents = [];
            $owed = 0;

            foreach ($mine as $week) {
                $parts = $week->items
                    ->map(function (SelfProgramItem $item) use ($student, $week, $totals, &$owed) {
                        $target = $this->selfProgram->targetFor($item, $student, $week);

                        if ($target <= 0) {
                            return null;
                        }

                        $done = $totals[$student->id][$item->id] ?? 0;
                        $owed += $done < $target ? 1 : 0;

                        return min(100.0, $done / $target * 100);
                    })
                    ->filter(fn ($percent) => $percent !== null);

                if ($parts->isNotEmpty()) {
                    $percents[] = $parts->avg();
                }
            }

            if ($percents !== []) {
                $out[$student->id]['self'] = [
                    'weeks' => count($percents),
                    'owed' => $owed,
                    'percent' => round(array_sum($percents) / count($percents), 1),
                ];
            }
        }
    }
}
