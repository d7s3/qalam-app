<?php

namespace App\Services;

use App\Models\AcademicCalendarEvent;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\StudentOdeAchievement;
use App\Models\StudentSelfProgramEntry;
use App\Support\Scope;
use App\Support\SelfProgramUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What the academy actually did, over a stretch of days.
 *
 * Six numbers were being asked for and none of them existed: lessons held,
 * hours taught, attendance at them, who was present today, verses recited and
 * pages read. Every one of them was already in the database and none was ever
 * added up — the attendance report counts a student's days, the mutun report a
 * student's progress, and nothing counted the academy.
 *
 * They are gathered in one class deliberately, because they are one question.
 * «Two hundred pages read» means nothing on its own; «two hundred pages by
 * sixty students across twelve lessons» is a sentence somebody can act on, and
 * that sentence cannot be assembled from six services that each know one word
 * of it. The derived figures at the bottom are the point of the class.
 *
 * Every figure passes through the same Scope the screens use, so a teacher's
 * total is his cohorts, a supervisor's is his programmes, and a manager's is
 * the academy — and no caller can forget to narrow one of them.
 */
class AcademyActivity
{
    private CarbonImmutable $from;

    private CarbonImmutable $to;

    /**
     * @param  Scope  $scope  whose reach these figures are counted within
     */
    public function __construct(
        private Scope $scope,
        CarbonImmutable|string $from,
        CarbonImmutable|string|null $to = null,
    ) {
        $this->from = CarbonImmutable::parse($from)->startOfDay();
        $this->to = CarbonImmutable::parse($to ?? $from)->startOfDay();
    }

    /** The figures for one day, which is what a dashboard asks for. */
    public static function today(?Scope $scope = null): self
    {
        $day = CarbonImmutable::now('Asia/Riyadh')->toDateString();

        return new self($scope ?? Scope::forRoute(), $day);
    }

    /**
     * Everything, in one pass, shaped for a screen.
     *
     * @return array<string, int|float|null>
     */
    public function all(): array
    {
        $lessons = $this->lessonsHeld();
        $attended = $this->attendances();
        $present = $attended['present'] + $attended['late'];

        return [
            'lessons_held' => $lessons,
            'lesson_hours' => $this->lessonHours(),
            'present' => $attended['present'],
            'late' => $attended['late'],
            'absent' => $attended['absent'],
            'excused' => $attended['excused'],
            'attended' => $present,
            'students_reached' => $this->studentsReached(),
            'verses_recited' => $this->versesRecited(),
            'pages_read' => $this->pagesRead(),

            // The figures that only mean something beside another. Null rather
            // than zero when the denominator is nothing: «zero pages a head»
            // on a day nobody attended reads as a failure, and it is silence.
            'attendance_rate' => $this->ratio($present, array_sum($attended)),
            'attended_per_lesson' => $this->ratio($present, $lessons),
            'pages_per_attendee' => $this->ratio($this->pagesRead(), $present),
            'verses_per_attendee' => $this->ratio($this->versesRecited(), $present),
        ];
    }

    /**
     * Lessons held — a cohort and a day on which somebody was marked.
     *
     * Nothing in the database says «a lesson happened»; the calendar says when
     * one is meant to, which is a different claim, and a term with no teachers
     * in it would still have a full calendar. A register taken is the only
     * evidence that a teacher stood in front of a cohort — so that is what is
     * counted, and a day nobody registered is a day that did not happen.
     */
    public function lessonsHeld(): int
    {
        // Single quotes on the separator: in SQLite a double-quoted token is an
        // identifier first and a string only if no column bears the name, which
        // is not a rule to leave a count resting on.
        return (int) $this->attendanceQuery()
            ->distinct()
            ->count(DB::raw("circle_id || '-' || date"));
    }

    /**
     * Hours taught, from the working times the stage keeps.
     *
     * Read per held day rather than multiplied out: stages sit at different
     * hours, a period may hold two sittings, and a day added to the calendar by
     * hand carries its own times.
     */
    public function lessonHours(): float
    {
        $minutes = 0;

        $held = $this->attendanceQuery()
            ->select('circle_id', 'date')
            ->distinct()
            ->get();

        $stageOf = Circle::whereIn('id', $held->pluck('circle_id')->unique())
            ->pluck('stage_id', 'id');

        foreach ($held as $lesson) {
            foreach (AcademicCalendarEvent::sessionsOn($lesson->date, $stageOf[$lesson->circle_id] ?? null) as $sitting) {
                $minutes += $this->minutesBetween($sitting['from'] ?? null, $sitting['to'] ?? null);
            }
        }

        return round($minutes / 60, 1);
    }

    /**
     * The registers taken, by what they say.
     *
     * @return array{present: int, late: int, absent: int, excused: int}
     */
    public function attendances(): array
    {
        $counted = $this->attendanceQuery()
            ->select('status', DB::raw('count(*) as tally'))
            ->groupBy('status')
            ->pluck('tally', 'status');

        return [
            'present' => (int) ($counted['present'] ?? 0),
            'late' => (int) ($counted['late'] ?? 0),
            'absent' => (int) ($counted['absent'] ?? 0),
            'excused' => (int) ($counted['excused'] ?? 0),
        ];
    }

    /** How many people were reached at all, rather than how many markings there were. */
    public function studentsReached(): int
    {
        return (int) $this->attendanceQuery()
            ->whereIn('status', ['present', 'late'])
            ->distinct()
            ->count('student_id');
    }

    /**
     * Verses recited — the lines of the mutun a teacher graded on these days.
     *
     * Counted from the day's own range rather than from a stored total, and
     * memorisation and review both count: a boy who revised forty lines said
     * forty lines.
     */
    public function versesRecited(): int
    {
        $graded = StudentOdeAchievement::query()
            ->join('ode_path_days', 'ode_path_days.id', '=', 'student_ode_achievements.ode_path_day_id')
            ->join('student_ode_plans', 'student_ode_plans.id', '=', 'student_ode_achievements.student_ode_plan_id')
            ->where(function ($q) {
                $q->whereBetween('student_ode_achievements.hifz_graded_at', $this->span())
                    ->orWhereBetween('student_ode_achievements.review_graded_at', $this->span());
            });

        $this->narrowStudents($graded, 'student_ode_plans.student_id');

        $lines = 0;

        foreach ($graded->get([
            'student_ode_achievements.hifz_graded_at',
            'student_ode_achievements.review_graded_at',
            'ode_path_days.from_verse_number',
            'ode_path_days.to_verse_number',
            'ode_path_days.review_from_verse_number',
            'ode_path_days.review_to_verse_number',
        ]) as $day) {
            if ($this->within($day->hifz_graded_at)) {
                $lines += $this->span_($day->from_verse_number, $day->to_verse_number);
            }

            if ($this->within($day->review_graded_at)) {
                $lines += $this->span_($day->review_from_verse_number, $day->review_to_verse_number);
            }
        }

        return $lines;
    }

    /**
     * Pages read, from the self programme.
     *
     * Only what was written in pages. The Quran plan is kept in ayahs and a
     * page of it is a conversion with a margin; adding the two would put a
     * measured number and an estimated one in the same total without saying so.
     */
    public function pagesRead(): int
    {
        $entries = StudentSelfProgramEntry::query()
            ->whereDate('entry_date', '>=', $this->from->toDateString())
            ->whereDate('entry_date', '<=', $this->to->toDateString())
            ->whereIn('self_program_item_id', SelfProgramItem::where('unit', SelfProgramUnit::PAGE)->select('id'));

        $this->narrowStudents($entries, 'student_self_program_entries.student_id');

        return (int) round($entries->sum('amount_done'));
    }

    /** The registers within reach and within the days asked about. */
    private function attendanceQuery()
    {
        // whereDate, not whereBetween: the column is cast, so it is stored as
        // «2026-09-14 00:00:00» and a plain comparison against «2026-09-14»
        // matches nothing at all — silently, and for every figure at once.
        $query = Attendance::query()
            ->whereDate('date', '>=', $this->from->toDateString())
            ->whereDate('date', '<=', $this->to->toDateString());

        $circles = $this->scope->circleIds();

        if ($circles !== null) {
            $query->whereIn('circle_id', $circles);
        }

        return $query;
    }

    /** Narrow a query that hangs off a student id, when the reach is narrowed. */
    private function narrowStudents($query, string $column): void
    {
        $circles = $this->scope->circleIds();

        if ($circles === null) {
            return;
        }

        $query->whereIn($column, DB::table('users')
            ->whereIn('circle_id', $circles)
            ->select('id'));
    }

    /** @return array<int, string> the two datetimes the graded-at columns fall between */
    private function span(): array
    {
        return [$this->from->toDateTimeString(), $this->to->endOfDay()->toDateTimeString()];
    }

    private function within(?string $at): bool
    {
        return $at !== null
            && CarbonImmutable::parse($at)->betweenIncluded($this->from, $this->to->endOfDay());
    }

    private function span_(?int $from, ?int $to): int
    {
        return $from !== null && $to !== null && $to >= $from ? ($to - $from + 1) : 0;
    }

    private function minutesBetween(?string $from, ?string $to): int
    {
        if (! $from || ! $to) {
            return 0;
        }

        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        // A sitting written as 21:00 to 00:30 runs past midnight, and the
        // subtraction would otherwise hand back a negative evening.
        return $end->lessThan($start)
            ? $start->diffInMinutes($end->addDay())
            : $start->diffInMinutes($end);
    }

    private function ratio(int|float $of, int|float $per): ?float
    {
        return $per > 0 ? round($of / $per, $per > 0 && $of / $per < 10 ? 2 : 1) : null;
    }
}
