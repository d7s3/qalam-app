<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Support\HijriDate;
use App\Support\Scope;
use App\Support\StudentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

/**
 * What a sheikh's home page counts: his group's week against the one before,
 * its last three months of memorisation, and each day of this week.
 *
 * Every figure comes from CircleReportService, so the home page and the
 * reports can never tell him two different things. They are worked out over
 * several ranges, so they are kept for ten minutes; who is waiting and how
 * the students stand are read fresh by the page itself.
 */
class SupervisorHomeService
{
    /** The days the academy meets, Sunday to Thursday, in a Saturday week. */
    private const MEETING_DAYS = [1, 2, 3, 4, 5];

    /**
     * @return array{
     *     attendance: array{now: int|null, before: int|null},
     *     pages: array{now: int, before: int},
     *     months: list<array{label: string, hifz: int, review: int}>,
     *     days: list<array{label: string, date: string, pages: int, upcoming: bool}>,
     *     weekLabel: string
     * }
     */
    public static function figures(User $supervisor): array
    {
        // A plain Carbon: the reports take one, and the application's now()
        // is immutable.
        $today = Carbon::parse(now('Asia/Riyadh')->toDateString());

        return Cache::remember(
            'supervisor-home:'.$supervisor->id.':'.$today->toDateString(),
            now()->addMinutes(10),
            fn () => self::work($supervisor, $today),
        );
    }

    private static function work(User $supervisor, Carbon $today): array
    {
        $students = Student::with('circle')
            ->whereIn('circle_id', Scope::for($supervisor, 'supervisor')->reachableCircleIds())
            ->get();

        $weekStart = $today->copy()->startOfWeek(Carbon::SATURDAY);
        $thisWeek = self::totals($students, $weekStart, $today);
        $lastWeek = self::totals($students, $weekStart->copy()->subWeek(), $weekStart->copy()->subDay());

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
     * How the students of his groups stand, in the order the statuses are
     * listed, leaving out the ones nobody holds.
     *
     * @return list<array{status: StudentStatus, count: int}>
     */
    public static function standing(User $supervisor): array
    {
        $counts = Student::whereIn('circle_id', Scope::for($supervisor, 'supervisor')->reachableCircleIds())
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
