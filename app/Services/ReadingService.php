<?php

namespace App\Services;

use App\Models\ReadingSession;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\Student;
use App\Models\StudentBook;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A student's own reading: his shelves, his sessions, and what they add up to.
 *
 * Every page he moves forward in a book is also counted towards المقروء in his
 * week, so the library is a way of keeping the self programme rather than a
 * second thing to keep.
 */
class ReadingService
{
    /** Longest a focus session may count, so a timer left running overnight counts as reading once. */
    public const LONGEST_SESSION = 240;

    /** A session this long or longer feeds the plant. */
    public const FOCUSED = 10;

    /** The plant's stages, by focused sessions finished. */
    public const PLANT = [0 => 'بذرة', 3 => 'برعم', 10 => 'نبتة', 25 => 'شجيرة', 50 => 'شجرة'];

    public function __construct(private SelfProgramService $selfProgram) {}

    /**
     * Write down that he has reached a page, after a focus session or without one.
     */
    public function advance(StudentBook $book, int $toPage, int $minutes = 0, ?ReadingSession $session = null): ReadingSession
    {
        $toPage = max(0, $book->total_pages ? min($toPage, $book->total_pages) : $toPage);
        $pages = max(0, $toPage - $book->current_page);

        $session = DB::transaction(function () use ($book, $toPage, $pages, $minutes, $session) {
            $session ??= new ReadingSession([
                'student_id' => $book->student_id,
                'student_book_id' => $book->id,
                'started_at' => now(),
            ]);
            $session->fill(['ended_at' => now(), 'minutes' => $minutes, 'pages' => $pages])->save();

            $book->current_page = max($book->current_page, $toPage);

            if (in_array($book->shelf, [StudentBook::WANT, StudentBook::DROPPED], true)) {
                $book->shelf = StudentBook::READING;
            }

            $book->started_on ??= today();

            if ($book->total_pages && $book->current_page >= $book->total_pages) {
                $book->shelf = StudentBook::READ;
                $book->finished_on ??= today();
            }

            $book->save();

            return $session;
        });

        if ($pages > 0) {
            $this->countTowardsWeek($book->student, $pages);
        }

        return $session;
    }

    /** Open a focus session on a book; it counts once he finishes it. */
    public function start(StudentBook $book): ReadingSession
    {
        return ReadingSession::create([
            'student_id' => $book->student_id,
            'student_book_id' => $book->id,
            'started_at' => now(),
        ]);
    }

    public function finish(ReadingSession $session, int $toPage): ReadingSession
    {
        $minutes = (int) min(self::LONGEST_SESSION, max(0, $session->started_at->diffInMinutes(now())));

        return $this->advance($session->book, $toPage, $minutes, $session);
    }

    /**
     * Add the pages to المقروء in his current week, when the week asks for it.
     * A week that asks for no reading is left alone.
     */
    private function countTowardsWeek(Student $student, int $pages): void
    {
        $item = $this->selfProgram->currentWeek($student)?->items
            ->first(fn (SelfProgramItem $item) => $item->getRawOriginal('track') === SelfProgramTrack::MAQROU
                && ($item->target_amount > 0 || filled($item->description)));

        if (! $item) {
            return;
        }

        $today = Carbon::today();

        try {
            $this->selfProgram->record(
                $student,
                $item,
                $this->selfProgram->recordedBy($student, $item, $today) + $pages,
                $today,
                by: $student,
            );
        } catch (\InvalidArgumentException $e) {
            report($e);
        }
    }

    /**
     * The days he read, newest first.
     *
     * @return Collection<int, string>
     */
    public function readingDays(Student $student): Collection
    {
        return ReadingSession::finished()
            ->where('student_id', $student->id)
            ->where(fn ($q) => $q->where('pages', '>', 0)->orWhere('minutes', '>', 0))
            ->pluck('ended_at')
            ->map(fn ($at) => Carbon::parse($at)->toDateString())
            ->unique()
            ->sortDesc()
            ->values();
    }

    /**
     * Days read in a row, ending today — or yesterday, since today is not over.
     *
     * @return array{current: int, best: int}
     */
    public function streak(Student $student, ?CarbonInterface $today = null): array
    {
        $days = $this->readingDays($student)->flip();
        $today = Carbon::parse(($today ?? Carbon::today())->toDateString());

        $day = isset($days[$today->toDateString()]) ? $today : $today->copy()->subDay();
        $current = 0;

        while (isset($days[$day->toDateString()])) {
            $current++;
            $day->subDay();
        }

        $best = 0;
        $run = 0;
        $previous = null;

        foreach ($days->keys()->sort()->values() as $date) {
            $run = $previous && Carbon::parse($previous)->addDay()->toDateString() === $date ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $date;
        }

        return ['current' => $current, 'best' => max($best, $current)];
    }

    /**
     * What he read between two days.
     *
     * @return array{pages: int, minutes: int, books: int}
     */
    public function totals(Student $student, CarbonInterface $from, CarbonInterface $to): array
    {
        $sessions = ReadingSession::finished()
            ->where('student_id', $student->id)
            ->whereBetween('ended_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        return [
            'pages' => (int) (clone $sessions)->sum('pages'),
            'minutes' => (int) (clone $sessions)->sum('minutes'),
            'books' => StudentBook::where('student_id', $student->id)
                ->whereBetween('finished_on', [$from->toDateString(), $to->toDateString()])
                ->count(),
        ];
    }

    /**
     * Pages a day for the last seven days, oldest first.
     *
     * @return array<string, int>
     */
    public function lastSevenDays(Student $student): array
    {
        $pages = ReadingSession::finished()
            ->where('student_id', $student->id)
            ->where('ended_at', '>=', Carbon::today()->subDays(6))
            ->get(['ended_at', 'pages'])
            ->groupBy(fn (ReadingSession $s) => $s->ended_at->toDateString())
            ->map(fn (Collection $day) => (int) $day->sum('pages'));

        return collect(range(6, 0))
            ->mapWithKeys(fn (int $back) => [
                $date = Carbon::today()->subDays($back)->toDateString() => $pages[$date] ?? 0,
            ])->all();
    }

    /**
     * How far his plant has grown, by the focused sessions he finished.
     *
     * @return array{stage: string, sessions: int, next: ?int}
     */
    public function plant(Student $student): array
    {
        $sessions = ReadingSession::finished()
            ->where('student_id', $student->id)
            ->where('minutes', '>=', self::FOCUSED)
            ->count();

        $stage = collect(self::PLANT)->filter(fn ($name, $from) => $sessions >= $from)->last();
        $next = collect(array_keys(self::PLANT))->first(fn ($from) => $from > $sessions);

        return ['stage' => $stage, 'sessions' => $sessions, 'next' => $next];
    }

    /**
     * His cohort this week by pages read, highest first.
     *
     * @return Collection<int, array{student: Student, pages: int}>
     */
    public function cohortWeek(Student $student): Collection
    {
        if (! $student->circle_id) {
            return collect();
        }

        $classmates = Student::where('circle_id', $student->circle_id)->get(['id', 'name']);
        $pages = ReadingSession::finished()
            ->whereIn('student_id', $classmates->pluck('id'))
            ->where('ended_at', '>=', Carbon::today()->startOfWeek(Carbon::SUNDAY))
            ->groupBy('student_id')
            ->selectRaw('student_id, sum(pages) as total')
            ->pluck('total', 'student_id');

        return $classmates
            ->map(fn (Student $one) => ['student' => $one, 'pages' => (int) ($pages[$one->id] ?? 0)])
            ->filter(fn (array $row) => $row['pages'] > 0 || $row['student']->id === $student->id)
            ->sortByDesc('pages')
            ->values();
    }

    /**
     * What his classmates are reading now.
     *
     * @return Collection<int, StudentBook>
     */
    public function classmatesReading(Student $student): Collection
    {
        if (! $student->circle_id) {
            return collect();
        }

        return StudentBook::with('student:id,name')
            ->where('shelf', StudentBook::READING)
            ->whereIn('student_id', Student::where('circle_id', $student->circle_id)->where('id', '!=', $student->id)->select('id'))
            ->latest('updated_at')
            ->limit(10)
            ->get();
    }
}
