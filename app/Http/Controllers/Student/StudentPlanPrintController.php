<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\HadithPathDay;
use App\Models\OdePathDay;
use App\Models\StudentHadithAchievement;
use App\Models\StudentHadithPlan;
use App\Models\StudentOdeAchievement;
use App\Models\StudentOdePlan;
use App\Models\StudentPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * A student's own plan, laid out for reading on screen and printing.
 *
 * The three kinds of plan keep their days in different shapes — Quran days
 * carry their own ranges, while ode and mutun days live on a shared path with
 * the student's grades stored separately — so each is flattened to the same
 * row shape here and the view stays one table.
 */
class StudentPlanPrintController extends Controller
{
    public function show(string $kind, int $id): View
    {
        $student = Auth::guard('student')->user();

        [$title, $subtitle, $rows, $startDate] = match ($kind) {
            'ode' => $this->odePlan($student, $id),
            'hadith' => $this->hadithPlan($student, $id),
            default => $this->quranPlan($student, $id),
        };

        return view('student.print-plan', [
            'student' => $student,
            'kind' => $kind,
            'title' => $title,
            'subtitle' => $subtitle,
            'rows' => $rows,
            'startDate' => $startDate,
        ]);
    }

    /**
     * @return array{0: string, 1: ?string, 2: array<int, array<string, mixed>>, 3: ?Carbon}
     */
    private function quranPlan($student, int $id): array
    {
        $plan = StudentPlan::where('student_id', $student->id)
            ->with(['days' => fn ($q) => $q->orderBy('date')])
            ->with('days.fromAyah.surah', 'days.toAyah.surah', 'days.reviewFromAyah.surah', 'days.reviewToAyah.surah')
            ->findOrFail($id);

        $title = match ($plan->plan_type) {
            'hifz' => 'خطة الحفظ',
            'review' => 'خطة المراجعة',
            default => 'خطة الحفظ والمراجعة',
        };

        $rows = $plan->days->map(fn ($day) => [
            'date' => $day->date,
            'day_name' => $day->day_name,
            'hifz' => in_array($plan->plan_type, ['hifz', 'hifz_review'], true)
                ? ['range' => $day->formatRange('hifz'), 'achievement' => $day->hifz_achievement]
                : null,
            'review' => in_array($plan->plan_type, ['review', 'hifz_review'], true)
                ? ['range' => $day->formatRange('review'), 'achievement' => $day->review_achievement]
                : null,
        ])->all();

        return [$title, null, $rows, $plan->start_date];
    }

    /**
     * @return array{0: string, 1: ?string, 2: array<int, array<string, mixed>>, 3: ?Carbon}
     */
    private function odePlan($student, int $id): array
    {
        $plan = StudentOdePlan::where('student_id', $student->id)
            ->with('path.ode')
            ->findOrFail($id);

        $achievements = StudentOdeAchievement::where('student_ode_plan_id', $plan->id)
            ->get()
            ->keyBy('ode_path_day_id');

        $rows = OdePathDay::where('ode_path_id', $plan->ode_path_id)
            ->orderBy('day_number')
            ->get()
            ->map(function (OdePathDay $day) use ($achievements) {
                $achievement = $achievements->get($day->id);

                return [
                    'date' => $day->date,
                    'day_name' => $day->day_name,
                    'hifz' => $day->from_verse_number
                        ? ['range' => $day->formatOdeRange('hifz'), 'achievement' => $achievement?->hifz_achievement]
                        : null,
                    'review' => $day->review_from_verse_number
                        ? ['range' => $day->formatOdeRange('review'), 'achievement' => $achievement?->review_achievement]
                        : null,
                ];
            })->all();

        return ['خطة المنظومة', $plan->path?->ode?->name, $rows, $plan->start_date];
    }

    /**
     * @return array{0: string, 1: ?string, 2: array<int, array<string, mixed>>, 3: ?Carbon}
     */
    private function hadithPlan($student, int $id): array
    {
        $plan = StudentHadithPlan::where('student_id', $student->id)
            ->with('path.text')
            ->findOrFail($id);

        $achievements = StudentHadithAchievement::where('student_hadith_plan_id', $plan->id)
            ->get()
            ->keyBy('hadith_path_day_id');

        $rows = HadithPathDay::where('hadith_path_id', $plan->hadith_path_id)
            ->orderBy('day_number')
            ->get()
            ->map(function (HadithPathDay $day) use ($achievements) {
                $achievement = $achievements->get($day->id);

                return [
                    'date' => $day->date,
                    'day_name' => $day->day_name,
                    'hifz' => $day->from_hadith_id
                        ? ['range' => $day->formatHadithRange('hifz'), 'achievement' => $achievement?->hifz_achievement]
                        : null,
                    'review' => $day->review_from_hadith_id
                        ? ['range' => $day->formatHadithRange('review'), 'achievement' => $achievement?->review_achievement]
                        : null,
                ];
            })->all();

        return ['خطة المتن', $plan->path?->text?->name, $rows, $plan->start_date];
    }
}
