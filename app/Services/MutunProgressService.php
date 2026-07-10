<?php

namespace App\Services;

use App\Models\Hadith;
use App\Models\OdeVerse;
use App\Models\Student;
use App\Models\StudentHadithPlan;
use App\Models\StudentOdePlan;
use Illuminate\Support\Collection;

class MutunProgressService
{
    /**
     * Active hadith (mutun) plans for a student, each with the full ordered text
     * and the completion state derived from graded hifz achievements.
     *
     * @return Collection<int, array{plan: StudentHadithPlan, hadiths: Collection<int, Hadith>, completedHadithIds: array<int, int>, completedLines: array<int, array<int, int>>, completedCount: int, totalCount: int}>
     */
    public static function hadithPlansProgress(Student $student): Collection
    {
        $plans = StudentHadithPlan::where('student_id', $student->id)
            ->where('status', 'active')
            ->with(['path.text', 'achievements.pathDay'])
            ->get();

        return $plans->map(function (StudentHadithPlan $plan) {
            $hadiths = Hadith::with('lines')
                ->where(function ($query) use ($plan) {
                    $query->where('hadith_text_id', $plan->path->hadith_text_id)
                        ->orWhereHas('chapter', function ($q) use ($plan) {
                            $q->where('hadith_text_id', $plan->path->hadith_text_id);
                        });
                })
                ->orderBy('hadith_chapter_id', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $orderedIds = $hadiths->pluck('id')->values();

            $completedHadithIds = [];
            $completedLines = [];

            foreach ($plan->achievements as $achievement) {
                $day = $achievement->pathDay;
                if (! $day || $achievement->hifz_achievement === null) {
                    continue;
                }

                if ($day->from_line_number && $day->to_line_number && $day->from_hadith_id) {
                    $lines = range($day->from_line_number, $day->to_line_number);
                    $completedLines[$day->from_hadith_id] = array_values(array_unique(array_merge(
                        $completedLines[$day->from_hadith_id] ?? [],
                        $lines
                    )));
                } elseif ($day->from_hadith_id && $day->to_hadith_id) {
                    $startIdx = $orderedIds->search($day->from_hadith_id);
                    $endIdx = $orderedIds->search($day->to_hadith_id);
                    if ($startIdx !== false && $endIdx !== false) {
                        $slice = $orderedIds->slice(min($startIdx, $endIdx), abs($endIdx - $startIdx) + 1);
                        foreach ($slice as $hadithId) {
                            $completedHadithIds[$hadithId] = true;
                        }
                    }
                }
            }

            // A hadith memorized line-by-line counts as completed once every line is covered.
            foreach ($hadiths as $hadith) {
                if (isset($completedHadithIds[$hadith->id])) {
                    continue;
                }
                $lineNumbers = $hadith->lines->pluck('line_number');
                if ($lineNumbers->isNotEmpty() && $lineNumbers->diff($completedLines[$hadith->id] ?? [])->isEmpty()) {
                    $completedHadithIds[$hadith->id] = true;
                }
            }

            return [
                'plan' => $plan,
                'hadiths' => $hadiths,
                'completedHadithIds' => array_keys($completedHadithIds),
                'completedLines' => $completedLines,
                'completedCount' => count($completedHadithIds),
                'totalCount' => $hadiths->count(),
            ];
        });
    }

    /**
     * Active ode plans for a student, each with the full verse list and the
     * verse numbers completed through graded hifz achievements.
     *
     * @return Collection<int, array{plan: StudentOdePlan, verses: Collection<int, OdeVerse>, completedVerseNumbers: array<int, int>, completedCount: int, totalCount: int}>
     */
    public static function odePlansProgress(Student $student): Collection
    {
        $plans = StudentOdePlan::where('student_id', $student->id)
            ->where('status', 'active')
            ->with(['path.ode.verses', 'achievements.pathDay'])
            ->get();

        return $plans->map(function (StudentOdePlan $plan) {
            $verses = $plan->path->ode?->verses ?? collect();

            $completedVerseNumbers = [];
            foreach ($plan->achievements as $achievement) {
                $day = $achievement->pathDay;
                if (! $day || $achievement->hifz_achievement === null) {
                    continue;
                }
                if ($day->from_verse_number && $day->to_verse_number) {
                    foreach (range($day->from_verse_number, $day->to_verse_number) as $verseNumber) {
                        $completedVerseNumbers[$verseNumber] = true;
                    }
                }
            }

            return [
                'plan' => $plan,
                'verses' => $verses,
                'completedVerseNumbers' => array_keys($completedVerseNumbers),
                'completedCount' => count($completedVerseNumbers),
                'totalCount' => $verses->count(),
            ];
        });
    }
}
