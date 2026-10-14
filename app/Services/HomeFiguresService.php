<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use App\Support\HijriDate;
use App\Support\Scope;
use App\Support\StudentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What a home page counts for the cohorts a person reaches — a sheikh's group,
 * a teacher's cohorts, the manager's academy: their week against the one
 * before, the last three months of memorisation, each day of this week, and
 * each cohort's week beside the others'.
 *
 * Every figure comes from CircleReportService, so the home page and the
 * reports can never tell him two different things. They are worked out over
 * several ranges, so they are kept for ten minutes; who is waiting and how
 * the students stand are read fresh by the page itself.
 */
class HomeFiguresService
{
    /** The days the academy meets, Sunday to Thursday, in a Saturday week. */
    private const MEETING_DAYS = [1, 2, 3, 4, 5];

    /**
     * @return array{
     *     attendance: array{now: int|null, before: int|null},
     *     pages: array{now: int, before: int},
     *     months: list<array{label: string, hifz: int, review: int}>,
     *     days: list<array{label: string, date: string, pages: int, upcoming: bool}>,
     *     cohorts: list<array{id: int, name: string, programme: string, teachers: string, students: int, attendance: int|null, pages: int, pagesBefore: int}>,
     *     weekLabel: string
     * }
     */
    public static function figures(User $user, string $role = 'supervisor'): array
    {
        // A plain Carbon: the reports take one, and the application's now()
        // is immutable.
        $today = Carbon::parse(now('Asia/Riyadh')->toDateString());

        return Cache::remember(
            'home-figures:v2:'.$role.':'.$user->id.':'.$today->toDateString(),
            now()->addMinutes(10),
            fn () => self::work($user, $role, $today),
        );
    }

    private static function work(User $user, string $role, Carbon $today): array
    {
        $circleIds = Scope::for($user, $role)->reachableCircleIds();
        $students = Student::with('circle')->whereIn('circle_id', $circleIds)->get();

        $weekStart = $today->copy()->startOfWeek(Carbon::SATURDAY);
        $thisWeekReport = CircleReportService::build($students, $weekStart, $today);
        $lastWeekReport = CircleReportService::build($students, $weekStart->copy()->subWeek(), $weekStart->copy()->subDay());
        $thisWeek = $thisWeekReport['totals'];
        $lastWeek = $lastWeekReport['totals'];

        return [
            // A week with nothing recorded has no rate to set this one against:
            // it read as a rise from nought.
            'attendance' => [
                'now' => $thisWeek['attendance']['total'] > 0 ? $thisWeek['attendance']['rate'] : null,
                'before' => $lastWeek['attendance']['total'] > 0 ? $lastWeek['attendance']['rate'] : null,
            ],
            'pages' => ['now' => $thisWeek['hifz']['pages'], 'before' => $lastWeek['hifz']['pages']],
            'months' => self::months($students, $today),
            'days' => self::days($students, $weekStart, $today),
            'cohorts' => self::cohorts($circleIds, $thisWeekReport['perStudent'], $lastWeekReport['perStudent']),
            'weekLabel' => HijriDate::dayMonth($weekStart->copy()->addDay()).' – '.HijriDate::dayMonth($weekStart->copy()->addDays(5)),
        ];
    }

    /** @return array<string, mixed> */
    private static function totals(EloquentCollection $students, Carbon $from, Carbon $to): array
    {
        return CircleReportService::build($students, $from, $to)['totals'];
    }

    /**
     * The last three Hijri months, oldest first, this one counted to today.
     *
     * @return list<array{label: string, hifz: int, review: int}>
     */
    private static function months(EloquentCollection $students, Carbon $today): array
    {
        $current = HijriDate::yearMonthOf($today);
        $months = [];

        foreach ([-2, -1, 0] as $shift) {
            $month = HijriDate::shiftMonth($current['year'], $current['month'], $shift);
            $grid = HijriDate::monthGrid($month['year'], $month['month']);
            $last = Carbon::parse($grid['last'])->min($today);
            $totals = self::totals($students, Carbon::parse($grid['first']), $last);

            $months[] = [
                'label' => HijriDate::format($grid['first'], 'MMMM'),
                'hifz' => (int) $totals['hifz']['pages'],
                'review' => (int) $totals['review']['pages'],
            ];
        }

        return $months;
    }

    /** @return list<array{label: string, date: string, pages: int, upcoming: bool}> */
    private static function days(EloquentCollection $students, Carbon $weekStart, Carbon $today): array
    {
        return collect(self::MEETING_DAYS)->map(function (int $offset) use ($students, $weekStart, $today) {
            $day = $weekStart->copy()->addDays($offset);
            $upcoming = $day->gt($today);

            return [
                'label' => HijriDate::weekday($day),
                'date' => $day->toDateString(),
                'pages' => $upcoming ? 0 : (int) self::totals($students, $day, $day)['hifz']['pages'],
                'upcoming' => $upcoming,
            ];
        })->all();
    }

    /**
     * Each cohort's week beside the one before: how many of its students take
     * part, how many of them came, and how much they memorised.
     *
     * A cohort with nobody in it yet is listed too, so a new one shows up the
     * day it is made rather than the day its first student is placed.
     *
     * @param  Collection<int, array<string, mixed>>  $thisWeek
     * @param  Collection<int, array<string, mixed>>  $lastWeek
     * @return list<array{id: int, name: string, programme: string, teachers: string, students: int, attendance: int|null, pages: int, pagesBefore: int}>
     */
    private static function cohorts(Collection $circleIds, Collection $thisWeek, Collection $lastWeek): array
    {
        $now = $thisWeek->groupBy(fn (array $row) => $row['student']->circle_id);
        $before = $lastWeek->groupBy(fn (array $row) => $row['student']->circle_id);

        return Circle::with(['stage', 'teachers'])
            ->whereIn('id', $circleIds)
            ->orderBy('name')
            ->get()
            ->map(function (Circle $circle) use ($now, $before) {
                $rows = $now->get($circle->id, collect());
                $expected = $rows->sum('expected_days');

                return [
                    'id' => $circle->id,
                    'name' => trim($circle->name),
                    'programme' => trim($circle->stage?->name ?? ''),
                    'teachers' => $circle->teachers->pluck('name')->implode('، '),
                    'students' => $rows->filter(fn (array $row) => StudentStatus::of($row['student']->status) === StudentStatus::Active)->count(),
                    'attendance' => $expected > 0 ? (int) round(($rows->sum('present') + $rows->sum('late')) / $expected * 100) : null,
                    'pages' => (int) $rows->sum('hifz_pages'),
                    'pagesBefore' => (int) $before->get($circle->id, collect())->sum('hifz_pages'),
                ];
            })
            ->all();
    }

    /**
     * A figure's movement against the period before, as the pill beside it
     * shows it — or null when there is nothing fair to set it against.
     *
     * @return array{up: bool, text: string}|null
     */
    public static function change(?float $now, ?float $before, bool $percent): ?array
    {
        if ($now === null || $before === null || ($percent && $before == 0.0)) {
            return null;
        }

        $value = $percent ? round(($now - $before) / $before * 100) : round($now - $before);

        return ['up' => $value >= 0, 'text' => ($value >= 0 ? '' : '−').abs($value).($percent ? '%' : '')];
    }

    /**
     * How the students of his groups stand, in the order the statuses are
     * listed, leaving out the ones nobody holds.
     *
     * @return list<array{status: StudentStatus, count: int}>
     */
    public static function standing(User $user, string $role = 'supervisor'): array
    {
        $counts = Student::whereIn('circle_id', Scope::for($user, $role)->reachableCircleIds())
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(StudentStatus::cases())
            ->map(fn (StudentStatus $status) => [
                'status' => $status,
                'count' => (int) $counts->filter(fn ($total, $stored) => StudentStatus::of($stored) === $status)->sum(),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }
}
