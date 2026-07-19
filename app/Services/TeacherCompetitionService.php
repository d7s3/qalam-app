<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\TeacherCompetition;
use Illuminate\Support\Collection;

class TeacherCompetitionService
{
    /**
     * Ranked standings for every participant, sorted by percentage desc.
     *
     * @return Collection<int, array{teacher: Teacher, score: int, max_score: int, percentage: float, details: array<int, int|null>, rank: int}>
     */
    public function getStandings(TeacherCompetition $competition): Collection
    {
        $criteria = $competition->criteria;
        $maxScore = (int) $criteria->sum('max_points');

        $scoresByTeacher = $competition->scores()->get()->groupBy('teacher_id');

        $standings = $competition->participants->map(function (Teacher $teacher) use ($criteria, $maxScore, $scoresByTeacher) {
            $teacherScores = $scoresByTeacher->get($teacher->id, collect());

            $details = [];
            foreach ($criteria as $criterion) {
                $score = $teacherScores->firstWhere('criterion_id', $criterion->id);
                $details[$criterion->id] = $score?->score;
            }

            $total = (int) collect($details)->filter(fn ($v) => $v !== null)->sum();

            return [
                'teacher' => $teacher,
                'score' => $total,
                'max_score' => $maxScore,
                'percentage' => $maxScore > 0 ? round($total / $maxScore * 100, 1) : 0.0,
                'details' => $details,
            ];
        });

        return $standings
            ->sortByDesc('percentage')
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    /**
     * The most relevant currently-running competition this teacher is
     * participating in, or null if none. Drives the teacher dashboard's
     * "show only the competition" override.
     */
    public function activeCompetitionFor(Teacher $teacher): ?TeacherCompetition
    {
        $today = now()->toDateString();

        return TeacherCompetition::whereHas('participants', function ($q) use ($teacher) {
            $q->where('users.id', $teacher->id);
        })
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->latest()
            ->first();
    }
}
