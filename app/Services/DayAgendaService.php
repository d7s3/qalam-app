<?php

namespace App\Services;

use App\Models\AcademicCalendarEvent;
use App\Models\HadithPathDay;
use App\Models\OccurrenceAttendance;
use App\Models\OdePathDay;
use App\Models\SelfProgramItem;
use App\Models\Student;
use App\Models\StudentHadithPlan;
use App\Models\StudentOdePlan;
use App\Models\StudentPlanDay;
use App\Models\Task;
use App\Models\User;
use App\Support\CalendarVisibility;
use App\Support\Scope;
use Illuminate\Support\Collection;

/**
 * What is on a person's day, by name.
 *
 * The calendar showed the events somebody entered into it, on days that already
 * held his hifz, his متن, his حديث and his ورد — all of them dated, none of
 * them visible. Every one of those keeps its own home and its own way of being
 * written; this reads them where they are and lays them out together.
 *
 * So it mirrors rather than owns. The one thing it does not mirror is the
 * appointment — the lesson, the meeting — which has no other home and lives in
 * the calendar itself.
 */
class DayAgendaService
{
    /**
     * @return array{
     *     occurrences: array<int, array{event: AcademicCalendarEvent, status: ?string}>,
     *     tasks: Collection<int, Task>,
     *     content: array<int, array{kind: string, label: string, detail: string}>
     * }
     */
    public static function forUser(User $user, string $role, string $date): array
    {
        return [
            'occurrences' => self::occurrences($user, $role, $date),
            'tasks' => self::tasks($user, $date),
            'content' => $user instanceof Student ? self::content($user, $date) : [],
        ];
    }

    /**
     * The appointments he is expected at today, each with his own answer.
     *
     * @return array<int, array{event: AcademicCalendarEvent, status: ?string}>
     */
    public static function occurrences(User $user, string $role, string $date): array
    {
        $stageIds = Scope::for($user, $role)->stageIds();

        $answered = OccurrenceAttendance::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->pluck('status', 'academic_calendar_event_id')
            ->all();

        return AcademicCalendarEvent::query()
            ->where('is_attendance_period', false)
            ->whereDate('start_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
            ->get()
            ->filter(fn (AcademicCalendarEvent $event) => $stageIds === null
                || ($event->stage_ids ?? []) === []
                || $stageIds->contains(fn ($id) => $event->appliesToStage((int) $id)))
            ->filter(fn (AcademicCalendarEvent $event) => CalendarVisibility::visibleTo($event, $role, $user))
            ->filter(fn (AcademicCalendarEvent $event) => $event->datesBetween($date, $date) !== [])
            ->map(fn (AcademicCalendarEvent $event) => [
                'event' => $event,
                'status' => $answered[$event->id] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * His tasks for today, and the ones whose day has passed unanswered.
     *
     * An overdue task belongs on today as much as on the day it was promised —
     * leaving it on a page nobody opens again is how it stops being chased.
     *
     * @return Collection<int, Task>
     */
    public static function tasks(User $user, string $date): Collection
    {
        return Task::query()
            ->with('category')
            ->where('assigned_to_id', $user->id)
            ->whereIn('assigned_to_type', array_values(MessagingService::MODELS))
            ->where(fn ($q) => $q
                ->whereDate('due_date', $date)
                ->orWhere(fn ($overdue) => $overdue
                    ->whereNotIn('status', Task::DONE)
                    ->whereDate('due_date', '<', $date)))
            ->orderBy('due_date')
            ->get();
    }

    /**
     * The work assigned to this day, gathered from wherever it is written.
     *
     * @return array<int, array{kind: string, label: string, detail: string}>
     */
    public static function content(Student $student, string $date): array
    {
        $rows = [];

        foreach (self::selfProgramTracks($student, $date) as $row) {
            $rows[] = $row;
        }

        $hadithPaths = StudentHadithPlan::where('student_id', $student->id)->pluck('hadith_path_id');

        foreach (HadithPathDay::whereIn('hadith_path_id', $hadithPaths)->whereDate('date', $date)->get() as $day) {
            $rows[] = [
                'kind' => 'hadith',
                'label' => __('الحديث'),
                'detail' => trim(($day->day_name ?? '').' '.($day->memorize_amount ?? '')),
            ];
        }

        $odePaths = StudentOdePlan::where('student_id', $student->id)->pluck('ode_path_id');

        foreach (OdePathDay::whereIn('ode_path_id', $odePaths)->whereDate('date', $date)->get() as $day) {
            $rows[] = [
                'kind' => 'ode',
                'label' => __('المتن'),
                'detail' => trim(($day->from_verse_number ?? '').' - '.($day->to_verse_number ?? '')),
            ];
        }

        foreach (StudentPlanDay::whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
            ->whereDate('date', $date)->get() as $day) {
            $rows[] = [
                'kind' => 'quran',
                'label' => __('الورد القرآني'),
                'detail' => $day->day_name ?? '',
            ];
        }

        return $rows;
    }

    /** @return array<int, array{kind: string, label: string, detail: string}> */
    private static function selfProgramTracks(Student $student, string $date): array
    {
        return SelfProgramItem::whereHas('week', fn ($q) => $q
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->where(fn ($w) => $w->where('circle_id', $student->circle_id)
                ->orWhere('stage_id', $student->stage_id)))
            ->get()
            ->map(fn (SelfProgramItem $item) => [
                'kind' => 'self-program',
                'label' => $item->track?->label() ?? __('البرنامج الذاتي'),
                'detail' => $item->target_amount.' '.$item->displayUnit(),
            ])
            ->all();
    }
}
