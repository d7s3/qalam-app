<?php

namespace App\Services;

use App\Models\Student;

class StudentStatusService
{
    /**
     * Change a student's status effective from a given date (defaults to today),
     * keeping the status history consistent:
     * - a different status closes the previous open history row and opens a new
     *   one starting at the effective date (backdating supported);
     * - the same status with a different date corrects the start date of the
     *   latest history row instead of stacking a duplicate entry.
     */
    public static function changeStatus(Student $student, string $status, ?string $effectiveDate = null, ?string $notes = null): void
    {
        $effectiveDate = $effectiveDate ?: now('Asia/Riyadh')->format('Y-m-d');

        $student->update(['status' => $status]);

        $lastHistory = $student->statusHistories()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($lastHistory && $lastHistory->status === $status) {
            if ($lastHistory->start_date->format('Y-m-d') !== $effectiveDate) {
                $lastHistory->update(['start_date' => $effectiveDate]);
            }

            return;
        }

        if ($lastHistory && ! $lastHistory->end_date) {
            $lastHistory->update(['end_date' => $effectiveDate]);
        }

        $student->statusHistories()->create([
            'status' => $status,
            'start_date' => $effectiveDate,
            'notes' => $notes,
        ]);
    }

    /**
     * Delete a wrong history entry and re-sync the student's current status to
     * the latest remaining row (when one exists).
     */
    public static function deleteHistoryEntry(Student $student, int $historyId): void
    {
        $student->statusHistories()->whereKey($historyId)->delete();

        $latest = $student->statusHistories()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $student->update(['status' => $latest->status]);
        }
    }
}
