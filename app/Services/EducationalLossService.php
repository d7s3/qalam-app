<?php

namespace App\Services;

use App\Models\AcademicCalendarEvent;
use App\Models\HadithPathDay;
use App\Models\OccurrenceAttendance;
use App\Models\OdePathDay;
use App\Models\SelfProgramItem;
use App\Models\Student;
use App\Models\StudentHadithAchievement;
use App\Models\StudentHadithPlan;
use App\Models\StudentOdeAchievement;
use App\Models\StudentOdePlan;
use App\Models\StudentPlanDay;
use App\Models\StudentSelfProgramEntry;
use App\Models\User;
use App\Support\Scope;
use Illuminate\Support\Collection;

/**
 * What a person was scheduled for and did not get.
 *
 * Two questions wear one name and are answered from different places, so they
 * are answered separately here:
 *
 *   الفاقد التربوي — a named thing on a day he did not attend. A lesson, a
 *   meeting. Read from the calendar against the occurrence register.
 *
 *   الفاقد العلمي — content assigned to a day and not done. A hadith not
 *   recited, verses not memorised, a track of the self programme left short.
 *   Read from the plans against their own achievements, and stored nowhere
 *   new: every one of these already carries its date.
 *
 * The first is an absence and the second is a shortfall, and they are not made
 * good by the same thing — one wants the meeting again, the other wants the
 * work.
 */
class EducationalLossService
{
    /**
     * The occurrences a person was expected at and has no attendance for.
     *
     * @return array<int, array{event: AcademicCalendarEvent, date: string, status: string}>
     */
    public static function formative(User $user, string $role, string $from, string $to): array
    {
        $stageIds = Scope::for($user, $role)->stageIds();

        $events = AcademicCalendarEvent::query()
            // Attendance periods say which days are working days; they are the
            // frame the appointments sit in, not appointments themselves.
            ->where('is_attendance_period', false)
            ->whereDate('start_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->get()
            ->filter(fn (AcademicCalendarEvent $event) => $stageIds === null
                ? true
                : $stageIds->contains(fn ($id) => $event->appliesToStage((int) $id))
                    || ($event->stage_ids ?? []) === []);

        $answered = self::answersFor($user, $from, $to);
        $losses = [];

        foreach ($events as $event) {
            foreach ($event->datesBetween($from, $to) as $date) {
                $status = $answered["{$event->id}|{$date}"] ?? null;

                if ($status !== null && in_array($status, OccurrenceAttendance::ATTENDED, true)) {
                    continue;
                }

                $losses[] = [
                    'event' => $event,
                    'date' => $date,
                    // Nothing written is still a miss; it is simply a miss
                    // nobody has explained yet.
                    'status' => $status ?? 'unrecorded',
                ];
            }
        }

        usort($losses, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $losses;
    }

    /**
     * Content that was due on a day inside the window and was not achieved.
     *
     * @return array<int, array{kind: string, label: string, date: string, expected: string, done: string}>
     */
    public static function scientific(Student $student, string $from, string $to): array
    {
        return array_merge(
            self::hadithShortfall($student, $from, $to),
            self::odeShortfall($student, $from, $to),
            self::quranShortfall($student, $from, $to),
            self::selfProgramShortfall($student, $from, $to),
        );
    }

    /** @return array<string, string> keyed "eventId|date" */
    private static function answersFor(User $user, string $from, string $to): array
    {
        return OccurrenceAttendance::where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->get(['academic_calendar_event_id', 'date', 'status'])
            ->mapWithKeys(fn (OccurrenceAttendance $row) => [
                $row->academic_calendar_event_id.'|'.$row->date->format('Y-m-d') => $row->status,
            ])
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private static function hadithShortfall(Student $student, string $from, string $to): array
    {
        $planIds = StudentHadithPlan::where('student_id', $student->id)->pluck('id', 'hadith_path_id');

        if ($planIds->isEmpty()) {
            return [];
        }

        $graded = StudentHadithAchievement::whereIn('student_hadith_plan_id', $planIds->values())
            ->whereNotNull('hifz_achievement')
            ->pluck('hadith_path_day_id')
            ->all();

        return HadithPathDay::whereIn('hadith_path_id', $planIds->keys())
            ->whereBetween('date', [$from, $to])
            ->whereNotIn('id', $graded)
            ->orderBy('date')
            ->get()
            ->map(fn (HadithPathDay $day) => [
                'kind' => 'hadith',
                'label' => __('حديث'),
                'date' => (string) $day->date,
                'expected' => (string) ($day->memorize_amount ?? ''),
                'done' => '',
            ])
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private static function odeShortfall(Student $student, string $from, string $to): array
    {
        $planIds = StudentOdePlan::where('student_id', $student->id)->pluck('id', 'ode_path_id');

        if ($planIds->isEmpty()) {
            return [];
        }

        $graded = StudentOdeAchievement::whereIn('student_ode_plan_id', $planIds->values())
            ->whereNotNull('hifz_achievement')
            ->pluck('ode_path_day_id')
            ->all();

        return OdePathDay::whereIn('ode_path_id', $planIds->keys())
            ->whereBetween('date', [$from, $to])
            ->whereNotIn('id', $graded)
            ->orderBy('date')
            ->get()
            ->map(fn (OdePathDay $day) => [
                'kind' => 'ode',
                'label' => __('متن'),
                'date' => (string) $day->date,
                'expected' => trim(($day->from_verse_number ?? '').' - '.($day->to_verse_number ?? '')),
                'done' => '',
            ])
            ->all();
    }

    /** @return array<int, array<string, string>> */
    private static function quranShortfall(Student $student, string $from, string $to): array
    {
        return StudentPlanDay::whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
            ->whereBetween('date', [$from, $to])
            ->whereNull('hifz_achievement')
            ->orderBy('date')
            ->get()
            ->map(fn (StudentPlanDay $day) => [
                'kind' => 'quran',
                'label' => __('الورد القرآني'),
                'date' => (string) $day->date,
                'expected' => '',
                'done' => '',
            ])
            ->all();
    }

    /**
     * A track of the self programme is not missed or kept — it is met in part.
     *
     * @return array<int, array<string, string>>
     */
    private static function selfProgramShortfall(Student $student, string $from, string $to): array
    {
        $items = SelfProgramItem::whereHas('week', fn ($q) => $q
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from)
            ->where(fn ($w) => $w->where('circle_id', $student->circle_id)
                ->orWhere('stage_id', $student->stage_id)))
            ->with('week')
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $done = StudentSelfProgramEntry::where('student_id', $student->id)
            ->whereIn('self_program_item_id', $items->pluck('id'))
            ->get()
            ->groupBy('self_program_item_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('amount_done'));

        return $items
            ->filter(fn (SelfProgramItem $item) => ($done[$item->id] ?? 0) < (float) $item->target_amount)
            ->map(fn (SelfProgramItem $item) => [
                'kind' => 'self-program',
                'label' => $item->track?->label() ?? __('البرنامج الذاتي'),
                'date' => (string) $item->week?->ends_on?->format('Y-m-d'),
                'expected' => $item->target_amount.' '.$item->displayUnit(),
                'done' => ($done[$item->id] ?? 0).' '.$item->displayUnit(),
            ])
            ->values()
            ->all();
    }
}
