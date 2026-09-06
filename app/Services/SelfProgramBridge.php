<?php

namespace App\Services;

use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Student;
use App\Models\StudentPlanDay;
use App\Models\StudentSelfProgramEntry;
use App\Support\MushafPages;

/**
 * What a recitation writes into the self programme.
 *
 * A student in a Quranic circle should not have to confirm his wird twice —
 * once to his teacher at the circle and once again on his own page. So when the
 * teacher grades a day, the pages that day covers are written into the Quran
 * wird for him, marked as coming from the recitation rather than from him.
 *
 * Memorisation and revision are counted by the measure each deserves — see
 * `CircleReportService::pageCountsPerStudent()` for why they differ — and then
 * added into the single figure the wird is read as.
 */
class SelfProgramBridge
{
    public function __construct(private SelfProgramService $programme) {}

    /**
     * Bring the wird entry for a plan day in line with how that day is graded.
     *
     * Called on every save of the day, so regrading corrects the entry and
     * ungrading removes it — a figure written from a grade must not outlive it.
     */
    public function syncFromPlanDay(StudentPlanDay $day): void
    {
        $student = $day->plan?->student;

        if (! $student instanceof Student) {
            return;
        }

        // Only a circle that memorises has recitations to read from; elsewhere
        // the wird is the student's own to confirm.
        if (! $student->circle?->is_quranic) {
            return;
        }

        $item = $this->wirdItemOn($student, $day);

        if (! $item) {
            return;
        }

        $this->programme->record(
            $student,
            $item,
            $this->pagesFor($day),
            $day->date,
            StudentSelfProgramEntry::SOURCE_TASMEEH,
        );
    }

    /**
     * Drop what a day wrote, for when the day itself goes.
     */
    public function clearForPlanDay(StudentPlanDay $day): void
    {
        $student = $day->plan?->student;

        if (! $student instanceof Student) {
            return;
        }

        $item = $this->wirdItemOn($student, $day);

        if (! $item) {
            return;
        }

        StudentSelfProgramEntry::where('student_id', $student->id)
            ->where('self_program_item_id', $item->id)
            ->where('source', StudentSelfProgramEntry::SOURCE_TASMEEH)
            ->whereDate('entry_date', $day->date)
            ->delete();
    }

    /**
     * The pages a graded day is worth.
     *
     * An ungraded half contributes nothing: the range was planned, not yet done.
     */
    private function pagesFor(StudentPlanDay $day): float
    {
        $pages = 0;

        if ($day->hifz_achievement !== null) {
            $pages += MushafPages::inRange($day->from_ayah_id, $day->to_ayah_id);
        }

        if ($day->review_achievement !== null) {
            $pages += MushafPages::inRange($day->review_from_ayah_id, $day->review_to_ayah_id);
        }

        return (float) $pages;
    }

    /**
     * The Quran wird of the week that covers this day, for this student's stage.
     */
    private function wirdItemOn(Student $student, StudentPlanDay $day): ?SelfProgramItem
    {
        if (! $day->date || ! $student->effective_stage_id) {
            return null;
        }

        $week = SelfProgramWeek::self()
            ->where('stage_id', $student->effective_stage_id)
            ->whereDate('starts_on', '<=', $day->date)
            ->whereDate('ends_on', '>=', $day->date)
            ->first();

        return $week?->items()->where('track', SelfProgramTrack::QURAN_WIRD)->first();
    }
}
