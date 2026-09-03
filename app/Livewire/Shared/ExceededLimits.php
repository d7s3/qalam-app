<?php

namespace App\Livewire\Shared;

use App\Models\Setting;
use App\Models\Student;
use App\Support\Scope;
use Livewire\Component;

class ExceededLimits extends Component
{
    public function render()
    {
        $absenceLimit = (int) Setting::getVal('absence_limit', 3);
        $latenessLimit = (int) Setting::getVal('lateness_limit', 5);
        $days = (int) Setting::getVal('calculation_period_days', 30);

        $cutoffDate = now()->subDays($days)->format('Y-m-d');

        $query = Student::query()
            ->with(['circle.stage', 'guardian'])
            ->whereRoleState(fn ($q) => $q->where('is_approved', true))
            ->withCount([
                'attendances as recent_absences_count' => function ($query) use ($cutoffDate) {
                    $query->where('status', 'absent')->where('date', '>=', $cutoffDate);
                },
                'attendances as recent_lateness_count' => function ($query) use ($cutoffDate) {
                    $query->where('status', 'late')->where('date', '>=', $cutoffDate);
                },
            ]);

        // Narrowed to the reader's reach, taken from the page he is standing on
        // rather than by asking the guards in turn — he may be signed in under
        // more than one, and the first to answer is not necessarily the right one.
        $query = Scope::forRoute()->applyToStudents($query);

        $students = collect();
        if ($query->count() > 0) {
            $students = $query->get()->filter(function ($student) use ($absenceLimit, $latenessLimit) {
                return $student->recent_absences_count >= $absenceLimit ||
                       $student->recent_lateness_count >= $latenessLimit;
            })->values(); // Reset numbering
        }

        return view('livewire.shared.exceeded-limits', [
            'students' => $students,
            'absenceLimit' => $absenceLimit,
            'latenessLimit' => $latenessLimit,
            'periodDays' => $days,
        ]);
    }
}
