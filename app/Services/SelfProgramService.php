<?php

namespace App\Services;

use App\Models\AcademicCalendarEvent;
use App\Models\SelfProgramDayOverride;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Models\Student;
use App\Models\StudentSelfProgramEntry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The arithmetic of the self programme.
 *
 * Two things are computed here rather than stored, because both follow from
 * what is already in the database and storing them would only let them drift:
 * the suggested daily split, and how far through a week a student is.
 */
class SelfProgramService
{
    /**
     * The threshold at which the enrichment programme opens.
     */
    public const ENRICHMENT_THRESHOLD = 50.0;

    /**
     * The week whose dates cover a day for a student.
     *
     * Written for his programme by the supervisor, or for his cohort alone by
     * his teacher. Where both cover the day the cohort's wins: it is the more
     * particular of the two, and it is the one his own teacher wrote.
     */
    public function currentWeek(Student $student, ?CarbonInterface $on = null): ?SelfProgramWeek
    {
        $day = ($on ?? Carbon::today())->toDateString();

        if (! $student->effective_stage_id) {
            return null;
        }

        return SelfProgramWeek::self()
            ->where(function ($q) use ($student) {
                $q->when($student->circle_id, fn ($c) => $c->where('circle_id', $student->circle_id))
                    ->orWhere(fn ($w) => $w->whereNull('circle_id')
                        ->where('stage_id', $student->effective_stage_id));
            })
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            // 0 sorts before 1, so a week naming a cohort comes before one that
            // names none.
            ->orderByRaw('circle_id is null')
            ->with('items')
            ->first();
    }

    /**
     * The enrichment week running for a student's circle.
     *
     * It is only his to open once he is half way through his own week — the
     * enrichment programme is a reward for keeping up, not a parallel demand —
     * so this answers null until that threshold is passed.
     */
    public function currentEnrichmentWeek(Student $student, ?CarbonInterface $on = null): ?SelfProgramWeek
    {
        if (! $student->circle_id) {
            return null;
        }

        $selfWeek = $this->currentWeek($student, $on);

        if (! $selfWeek || ! $this->enrichmentUnlocked($student, $selfWeek)) {
            return null;
        }

        $day = ($on ?? Carbon::today())->toDateString();

        return SelfProgramWeek::enrichment()
            ->where('circle_id', $student->circle_id)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->with('items')
            ->first();
    }

    /**
     * The week after a given one, by number rather than by date, so a gap in
     * the calendar between terms does not lose it.
     */
    public function nextWeek(SelfProgramWeek $week): ?SelfProgramWeek
    {
        return $this->siblingWeeks($week)
            ->where('week_number', '>', $week->week_number)
            ->orderBy('week_number')
            ->with('items')
            ->first();
    }

    /**
     * Whether a student may open a week.
     *
     * A week is open once its dates arrive, and stays open afterwards so a
     * student can still finish what he left. On top of that a circle may let
     * finishing a week open the next one early — the teacher's choice, off
     * unless he turns it on.
     *
     * Reaching an early week means every week between the last one the calendar
     * has opened and this one is finished. That chain is walked in a loop over
     * weeks loaded once, rather than by recursing a week at a time: by the
     * fortieth week of a year the recursion was forty frames deep and eighty
     * queries wide to answer one yes or no.
     */
    public function canOpen(Student $student, SelfProgramWeek $week, ?CarbonInterface $on = null): bool
    {
        $today = $on ?? Carbon::today();

        if ($week->starts_on->lte($today)) {
            return true;
        }

        if (! $student->circle?->self_program_unlock_on_completion) {
            return false;
        }

        $preceding = $this->siblingWeeks($week)
            ->where('week_number', '<', $week->week_number)
            ->orderBy('week_number')
            ->with('items')
            ->get();

        // The chain starts at the last week the calendar itself opened; without
        // one there is nothing the student could have finished yet.
        $chainStart = $preceding->last(fn (SelfProgramWeek $w) => $w->starts_on->lte($today));

        if (! $chainStart) {
            return false;
        }

        $chain = $preceding->filter(
            fn (SelfProgramWeek $w) => $w->week_number >= $chainStart->week_number,
        );

        foreach ($this->overallForWeeks($student, $chain) as $overall) {
            if ($overall < 100.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * The working days a week spans, as Y-m-d strings.
     *
     * `AcademicCalendarEvent` is the one definition of a working day in this
     * application — plans are laid out on it and attendance is measured against
     * it — so the split honours the same holidays and closures everything else
     * does, per stage.
     *
     * @return array<int, string>
     */
    public function workingDays(SelfProgramWeek $week): array
    {
        return AcademicCalendarEvent::workingDaysBetween(
            $week->starts_on,
            $week->ends_on,
            $week->effectiveStageId(),
        );
    }

    /**
     * The suggested amount for each working day of the week.
     *
     * Each day is offered what is left of the week divided by the days left to
     * do it in, so falling behind on Sunday raises Monday's share on its own
     * and the week never asks for more than it holds. A teacher's override for
     * the student, or failing that for his circle, replaces the computed figure
     * for that day.
     *
     * What counts as already done is everything recorded before that day, not
     * only what fell on a working day: a recitation graded on a Friday is work
     * the student did, and leaving it out told him to cover the whole week
     * again while his own progress bar said otherwise.
     *
     * @return array<string, float> keyed by Y-m-d
     */
    public function dailyPlan(SelfProgramItem $item, Student $student): array
    {
        $days = $this->workingDays($item->week);
        $target = (float) $item->target_amount;

        if ($days === [] || $target <= 0) {
            return array_fill_keys($days, 0.0);
        }

        $overrides = $this->overridesFor($item, $student);
        $doneByDay = $this->doneByDay($item, $student);

        $plan = [];

        foreach ($days as $index => $day) {
            $doneBefore = 0.0;

            foreach ($doneByDay as $date => $amount) {
                if ($date < $day) {
                    $doneBefore += $amount;
                }
            }

            $remaining = max(0.0, $target - $doneBefore);
            $daysLeft = count($days) - $index;

            $plan[$day] = $overrides[$day] ?? round($remaining / $daysLeft, 2);
        }

        return $plan;
    }

    /**
     * A teacher's overrides for this item, the student's own beating his
     * circle's.
     *
     * @return array<string, float>
     */
    private function overridesFor(SelfProgramItem $item, Student $student): array
    {
        $rows = SelfProgramDayOverride::where('self_program_item_id', $item->id)
            ->where(function ($query) use ($student) {
                $query->where('student_id', $student->id)
                    ->orWhere(function ($sub) use ($student) {
                        $sub->whereNull('student_id')->where('circle_id', $student->circle_id);
                    });
            })
            ->orderByRaw('student_id is null desc')
            ->get();

        $overrides = [];

        foreach ($rows as $row) {
            $overrides[$row->day_date->toDateString()] = (float) $row->amount;
        }

        return $overrides;
    }

    /**
     * What the student has done against this item, totalled per day.
     *
     * @return array<string, float>
     */
    public function doneByDay(SelfProgramItem $item, Student $student): array
    {
        return StudentSelfProgramEntry::where('self_program_item_id', $item->id)
            ->where('student_id', $student->id)
            ->get()
            ->groupBy(fn (StudentSelfProgramEntry $entry) => $entry->entry_date->toDateString())
            ->map(fn (Collection $group) => (float) $group->sum('amount_done'))
            ->all();
    }

    /**
     * What the student put down himself against this item on a day, apart from
     * anything a recitation wrote for him.
     */
    public function recordedBy(
        Student $student,
        SelfProgramItem $item,
        CarbonInterface $on,
        string $source = StudentSelfProgramEntry::SOURCE_STUDENT,
    ): float {
        return (float) StudentSelfProgramEntry::where('student_id', $student->id)
            ->where('self_program_item_id', $item->id)
            ->where('source', $source)
            ->whereDate('entry_date', $on->toDateString())
            ->sum('amount_done');
    }

    /**
     * How far a student is through a week.
     *
     * A track the supervisor left at zero is not part of this week's ask, so it
     * is left out of the average entirely rather than counted as nothing done —
     * otherwise a week written for three tracks could never reach 100%, and a
     * student could never finish it however much he did.
     *
     * The overall figure is the mean of the tracks' own percentages, each capped
     * at 100%, because the tracks are measured in different units: pages,
     * lessons and hadiths do not add up to a number that means anything, and
     * capping stops excelling at one track from covering for neglecting another.
     *
     * @return array{tracks: array<int, array{item: SelfProgramItem, done: float, target: float, percent: float}>, overall: float}
     */
    public function weekProgress(Student $student, SelfProgramWeek $week): array
    {
        $totals = StudentSelfProgramEntry::where('student_id', $student->id)
            ->whereIn('self_program_item_id', $week->items->pluck('id'))
            ->selectRaw('self_program_item_id, sum(amount_done) as total')
            ->groupBy('self_program_item_id')
            ->pluck('total', 'self_program_item_id')
            ->map(fn ($total) => (float) $total)
            ->all();

        return $this->readWeek($week, $totals);
    }

    /**
     * The same reading, from totals already in hand.
     *
     * @param  array<int, float>  $totals  keyed by item id
     * @return array{tracks: array<int, array{item: SelfProgramItem, done: float, target: float, percent: float}>, overall: float}
     */
    private function readWeek(SelfProgramWeek $week, array $totals): array
    {
        $tracks = [];
        $percentages = [];

        foreach ($week->items as $item) {
            $target = (float) $item->target_amount;
            $done = (float) ($totals[$item->id] ?? 0);
            $percent = $target > 0 ? min(100.0, $done / $target * 100) : 0.0;

            $tracks[] = [
                'item' => $item,
                'done' => $done,
                'target' => $target,
                'percent' => round($percent, 1),
            ];

            if ($target > 0) {
                $percentages[] = $percent;
            }
        }

        return [
            'tracks' => $tracks,
            'overall' => $percentages === [] ? 0.0 : round(array_sum($percentages) / count($percentages), 1),
        ];
    }

    /**
     * How far one student is through several weeks, in a single query.
     *
     * @param  Collection<int, SelfProgramWeek>  $weeks
     * @return array<int, float> keyed by week id
     */
    public function overallForWeeks(Student $student, Collection $weeks): array
    {
        if ($weeks->isEmpty()) {
            return [];
        }

        $weeks->each->loadMissing('items');

        $totals = StudentSelfProgramEntry::where('student_id', $student->id)
            ->whereIn('self_program_item_id', $weeks->pluck('items')->flatten()->pluck('id'))
            ->selectRaw('self_program_item_id, sum(amount_done) as total')
            ->groupBy('self_program_item_id')
            ->pluck('total', 'self_program_item_id')
            ->map(fn ($total) => (float) $total)
            ->all();

        $out = [];

        foreach ($weeks as $week) {
            $out[$week->id] = $this->readWeek($week, $totals)['overall'];
        }

        return $out;
    }

    /**
     * Whether the enrichment programme has opened for this week.
     *
     * Callers that have already read the week pass its figure in; the reading
     * is the most repeated calculation in the feature, and recomputing it here
     * doubled the cost of every screen that shows a bar beside a lock.
     */
    public function enrichmentUnlocked(Student $student, SelfProgramWeek $week, ?float $overall = null): bool
    {
        return ($overall ?? $this->weekProgress($student, $week)['overall']) >= self::ENRICHMENT_THRESHOLD;
    }

    /**
     * What earlier weeks still owe.
     *
     * Carrying a shortfall forward into the next week was deliberately not done:
     * it would push a week's bar past its own total and leave a student starting
     * in debt, and it would muddle the half-week the enrichment programme turns
     * on. The shortfall is shown apart instead, as arrears, where it stays
     * legible and can still be settled.
     *
     * @return array<int, array{item: SelfProgramItem, week: SelfProgramWeek, done: float, target: float, remaining: float}>
     */
    public function arrears(Student $student, ?CarbonInterface $on = null): array
    {
        $today = $on ?? Carbon::today();

        if (! $student->effective_stage_id) {
            return [];
        }

        $weeks = SelfProgramWeek::self()
            ->where('stage_id', $student->effective_stage_id)
            ->whereDate('ends_on', '<', $today->toDateString())
            ->with('items')
            ->orderBy('week_number')
            ->get();

        if ($weeks->isEmpty()) {
            return [];
        }

        $totals = StudentSelfProgramEntry::where('student_id', $student->id)
            ->whereIn('self_program_item_id', $weeks->pluck('items')->flatten()->pluck('id'))
            ->selectRaw('self_program_item_id, sum(amount_done) as total')
            ->groupBy('self_program_item_id')
            ->pluck('total', 'self_program_item_id');

        $arrears = [];

        foreach ($weeks as $week) {
            foreach ($week->items as $item) {
                $target = (float) $item->target_amount;

                // Nothing was asked for, so nothing can be owed.
                if ($target <= 0) {
                    continue;
                }

                $done = (float) ($totals[$item->id] ?? 0);

                if ($done >= $target) {
                    continue;
                }

                $arrears[] = [
                    'item' => $item,
                    'week' => $week,
                    'done' => $done,
                    'target' => $target,
                    'remaining' => round($target - $done, 2),
                ];
            }
        }

        return $arrears;
    }

    /**
     * How a whole set of students stands, in a fixed handful of queries.
     *
     * Asked one student at a time this cost six queries each — a stage of two
     * hundred meant twelve hundred, and the same week was fetched once per
     * student. Everything the table needs is loaded in four queries here and
     * matched up in memory instead.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array{student: Student, week: ?SelfProgramWeek, overall: float|null, tracks: array<int, array<string, mixed>>, unlocked: bool, arrears: int}>
     */
    public function progressForStudents(Collection $students, ?CarbonInterface $on = null): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        $day = ($on ?? Carbon::today())->toDateString();
        $students->loadMissing('circle');

        $stageIds = $students->map(fn (Student $s) => $s->effective_stage_id)->filter()->unique()->values();

        if ($stageIds->isEmpty()) {
            return [];
        }

        $current = SelfProgramWeek::self()
            ->whereIn('stage_id', $stageIds)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->with('items')
            ->get()
            ->keyBy('stage_id');

        $past = SelfProgramWeek::self()
            ->whereIn('stage_id', $stageIds)
            ->whereDate('ends_on', '<', $day)
            ->with('items')
            ->get()
            ->groupBy('stage_id');

        $itemIds = $current->pluck('items')->flatten()->pluck('id')
            ->merge($past->flatten(1)->pluck('items')->flatten()->pluck('id'))
            ->unique()
            ->values();

        $totals = [];

        if ($itemIds->isNotEmpty()) {
            StudentSelfProgramEntry::whereIn('student_id', $students->pluck('id'))
                ->whereIn('self_program_item_id', $itemIds)
                ->selectRaw('student_id, self_program_item_id, sum(amount_done) as total')
                ->groupBy('student_id', 'self_program_item_id')
                ->get()
                ->each(function ($row) use (&$totals) {
                    $totals[$row->student_id][$row->self_program_item_id] = (float) $row->total;
                });
        }

        $out = [];

        foreach ($students as $student) {
            $stageId = $student->effective_stage_id;
            $week = $stageId ? $current->get($stageId) : null;
            $mine = $totals[$student->id] ?? [];

            $reading = $week ? $this->readWeek($week, $mine) : null;
            $overall = $reading['overall'] ?? null;

            $owed = 0;

            foreach ($past->get($stageId) ?? collect() as $old) {
                foreach ($old->items as $item) {
                    $target = (float) $item->target_amount;

                    if ($target > 0 && ($mine[$item->id] ?? 0) < $target) {
                        $owed++;
                    }
                }
            }

            $out[$student->id] = [
                'student' => $student,
                'week' => $week,
                'overall' => $overall,
                'tracks' => $reading['tracks'] ?? [],
                'unlocked' => $overall !== null && $overall >= self::ENRICHMENT_THRESHOLD,
                'arrears' => $owed,
            ];
        }

        return $out;
    }

    /**
     * Record what a student did against a track on a day.
     *
     * An amount of zero clears the day rather than storing a nought, so a
     * student who unticks a day leaves nothing behind for a report to read as
     * work he did.
     */
    public function record(
        Student $student,
        SelfProgramItem $item,
        float $amount,
        ?CarbonInterface $on = null,
        string $source = StudentSelfProgramEntry::SOURCE_STUDENT,
    ): ?StudentSelfProgramEntry {
        // Bound as a Carbon rather than a bare "Y-m-d": the `date` cast writes
        // the full "Y-m-d H:i:s" form, and on SQLite the column is text, so a
        // bare string would compare unequal to every row it ought to match.
        $day = ($on ?? Carbon::today())->copy()->startOfDay();

        $keys = [
            'student_id' => $student->id,
            'self_program_item_id' => $item->id,
            'entry_date' => $day,
            'source' => $source,
        ];

        if ($amount <= 0) {
            // Deleted one model at a time, not by a mass delete on the query: a
            // mass delete fires no model events, and the week's milestone points
            // are withdrawn by the entry's own `deleted` hook. There is at most
            // one row here — the four keys are the table's unique index.
            StudentSelfProgramEntry::where($keys)->get()->each->delete();

            return null;
        }

        return StudentSelfProgramEntry::updateOrCreate($keys, ['amount_done' => round($amount, 2)]);
    }

    /**
     * The other weeks of the same programme — the same stage's, or the same
     * circle's for an enrichment one.
     *
     * @return Builder<SelfProgramWeek>
     */
    private function siblingWeeks(SelfProgramWeek $week)
    {
        return SelfProgramWeek::where('program_type', $week->program_type)
            ->where('stage_id', $week->stage_id)
            ->where('circle_id', $week->circle_id);
    }
}
