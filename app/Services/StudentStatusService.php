<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Auth;

class StudentStatusService
{
    /**
     * Change a student's status effective from a given date (defaults to today),
     * keeping the status history consistent:
     * - a different status closes the previous open history row and opens a new
     *   one starting at the effective date (backdating supported);
     * - the same status with a different date corrects the start date of the
     *   latest history row instead of stacking a duplicate entry;
     * - scheduled future rows (e.g. a pending auto-return) are superseded by any
     *   new manual decision and removed first.
     */
    public static function changeStatus(Student $student, string $status, ?string $effectiveDate = null, ?string $notes = null): void
    {
        $today = now('Asia/Riyadh')->format('Y-m-d');
        $effectiveDate = $effectiveDate ?: $today;

        // A new decision supersedes any scheduled (future-dated) rows.
        $student->statusHistories()->whereDate('start_date', '>', $today)->delete();

        $lastHistory = $student->statusHistories()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($lastHistory && $lastHistory->status === $status) {
            // Correcting the effective date of the latest change: it may not slide
            // back past the start of the period before it (timeline stays ordered).
            $previous = $student->statusHistories()
                ->whereKeyNot($lastHistory->id)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

            if ($previous && $effectiveDate <= $previous->start_date->format('Y-m-d')) {
                throw new \InvalidArgumentException(
                    'التاريخ المختار يسبق بداية الحالة السابقة ('.$previous->start_date->format('Y-m-d').'). احذف السجلات الخاطئة من سجل الحالات أولاً.'
                );
            }

            $student->update(['status' => $status]);

            if ($lastHistory->start_date->format('Y-m-d') !== $effectiveDate) {
                $lastHistory->update(array_merge(
                    ['start_date' => $effectiveDate],
                    $notes ? ['notes' => $notes] : [],
                    self::changedByMeta(),
                ));

                if ($previous) {
                    $previous->update(['end_date' => $effectiveDate]);
                }
            }

            return;
        }

        // The timeline is append-only: a new status may not start before the
        // latest recorded period. Wrong entries are fixed by deleting them.
        if ($lastHistory && $effectiveDate < $lastHistory->start_date->format('Y-m-d')) {
            throw new \InvalidArgumentException(
                'التاريخ المختار يسبق بداية الحالة الحالية ('.$lastHistory->start_date->format('Y-m-d').'). احذف السجلات الخاطئة من سجل الحالات أولاً.'
            );
        }

        $student->update(['status' => $status]);

        if ($lastHistory && ! $lastHistory->end_date) {
            $lastHistory->update(['end_date' => $effectiveDate]);
        }

        $student->statusHistories()->create(array_merge([
            'status' => $status,
            'start_date' => $effectiveDate,
            'notes' => $notes,
        ], self::changedByMeta()));
    }

    /**
     * Suspend a student with an automatic return: a scheduled "active" row is
     * written at the return date, and the daily status sync flips the cached
     * column when that day arrives.
     */
    public static function suspendWithReturn(Student $student, string $effectiveDate, string $returnDate, ?string $notes = null): void
    {
        if ($returnDate <= $effectiveDate) {
            throw new \InvalidArgumentException('تاريخ العودة يجب أن يكون بعد تاريخ بداية الإيقاف.');
        }

        self::changeStatus($student, 'suspended', $effectiveDate, $notes);

        $suspension = $student->statusHistories()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
        $suspension->update(['end_date' => $returnDate]);

        $student->statusHistories()->create(array_merge([
            'status' => 'active',
            'start_date' => $returnDate,
            'notes' => 'عودة تلقائية بعد انتهاء الإيقاف',
        ], self::changedByMeta()));
    }

    /**
     * The student's status on a given date per the history timeline, falling
     * back to the current column for students without covering rows.
     */
    public static function statusOn(Student $student, string $date): string
    {
        $row = $student->statusHistories()
            ->whereDate('start_date', '<=', $date)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        return $row ? $row->status : ($student->status ?: 'active');
    }

    /**
     * Delete a wrong history entry and re-sync the student's current status to
     * the effective row for today (when one exists).
     */
    public static function deleteHistoryEntry(Student $student, int $historyId): void
    {
        $student->statusHistories()->whereKey($historyId)->delete();

        $today = now('Asia/Riyadh')->format('Y-m-d');
        $effectiveToday = $student->statusHistories()
            ->whereDate('start_date', '<=', $today)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($effectiveToday) {
            $student->update(['status' => $effectiveToday->status]);
        }
    }

    /**
     * The acting user recorded on each history row (role + name snapshot).
     *
     * @return array{changed_by_role: ?string, changed_by_name: ?string}
     */
    protected static function changedByMeta(): array
    {
        foreach (['manager', 'supervisor', 'teacher'] as $role) {
            if ($user = Auth::guard($role)->user()) {
                return ['changed_by_role' => $role, 'changed_by_name' => $user->name];
            }
        }

        return ['changed_by_role' => null, 'changed_by_name' => null];
    }
}
