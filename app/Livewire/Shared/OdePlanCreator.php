<?php

namespace App\Livewire\Shared;

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\Ode;
use App\Models\OdeVerse;
use App\Models\Student;
use App\Models\StudentOdePlan;
use App\Models\StudentOdePlanDay;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Component;

class OdePlanCreator extends Component
{
    public $userRole; // 'supervisor' or 'teacher'

    // Form inputs
    public ?int $studentId = null;

    public ?int $odeId = null;

    public string $startDate = '';

    public array $activeDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday'];

    // Hifz configuration
    public int $hifzStart = 1;

    public int $hifzEnd = 1;

    public int $hifzRate = 5;

    // Review configuration (optional)
    public bool $hasReview = false;

    public int $reviewStart = 1;

    public int $reviewEnd = 1;

    public int $reviewRate = 10;

    // Preview and state
    public array $planDays = [];

    public bool $isGenerated = false;

    public function mount(?int $studentId = null): void
    {
        if (auth()->guard('supervisor')->check()) {
            $this->userRole = 'supervisor';
        } else {
            $this->userRole = 'teacher';
        }

        $this->startDate = now()->format('Y-m-d');

        if ($studentId) {
            $this->studentId = $studentId;
        }

        // Auto-fill active days from the current attendance period if any
        $this->autoFillActiveDays();
    }

    public function autoFillActiveDays(): void
    {
        $startDateObj = Carbon::parse($this->startDate ?: now());
        $attendancePeriod = AcademicCalendarEvent::where('is_attendance_period', true)
            ->where('start_date', '<=', $startDateObj->format('Y-m-d'))
            ->where('end_date', '>=', $startDateObj->format('Y-m-d'))
            ->first();

        if ($attendancePeriod && ! empty($attendancePeriod->weekdays)) {
            $mapping = [
                1 => 'Sunday',
                2 => 'Monday',
                3 => 'Tuesday',
                4 => 'Wednesday',
                5 => 'Thursday',
                6 => 'Friday',
                7 => 'Saturday',
            ];

            $this->activeDays = array_map(fn ($wd) => $mapping[$wd], $attendancePeriod->weekdays);
        }
    }

    public function updatedOdeId($value): void
    {
        if ($value) {
            $maxVerse = OdeVerse::where('ode_id', $value)->max('verse_number') ?: 1;
            $this->hifzStart = 1;
            $this->hifzEnd = $maxVerse;
            $this->reviewStart = 1;
            $this->reviewEnd = $maxVerse;
        }
    }

    public function generatePreview(): void
    {
        $this->validate([
            'studentId' => 'required|exists:students,id',
            'odeId' => 'required|exists:odes,id',
            'startDate' => 'required|date',
            'activeDays' => 'required|array|min:1',
            'hifzStart' => 'required|integer|min:1',
            'hifzEnd' => 'required|integer|gte:hifzStart',
            'hifzRate' => 'required|integer|min:1',
            'reviewStart' => 'required_if:hasReview,true|nullable|integer|min:1',
            'reviewEnd' => 'required_if:hasReview,true|nullable|integer|gte:reviewStart',
            'reviewRate' => 'required_if:hasReview,true|nullable|integer|min:1',
        ]);

        $maxVerse = OdeVerse::where('ode_id', $this->odeId)->max('verse_number') ?: 1;

        if ($this->hifzEnd > $maxVerse) {
            $this->addError('hifzEnd', "نهاية الحفظ لا يمكن أن تتجاوز عدد أبيات المنظومة وهو {$maxVerse} بيتاً.");

            return;
        }

        if ($this->hasReview && $this->reviewEnd > $maxVerse) {
            $this->addError('reviewEnd', "نهاية المراجعة لا يمكن أن تتجاوز عدد أبيات المنظومة وهو {$maxVerse} بيتاً.");

            return;
        }

        // Calculate total days needed
        $hifzTotalVerses = $this->hifzEnd - $this->hifzStart + 1;
        $hifzDays = (int) ceil($hifzTotalVerses / $this->hifzRate);

        $daysNeeded = $hifzDays;

        if ($this->hasReview) {
            $reviewTotalVerses = $this->reviewEnd - $this->reviewStart + 1;
            $reviewDays = (int) ceil($reviewTotalVerses / $this->reviewRate);
            $daysNeeded = max($hifzDays, $reviewDays);
        }

        $this->planDays = [];
        $currentDate = Carbon::parse($this->startDate);
        $count = 0;

        $attendancePeriods = AcademicCalendarEvent::where('is_attendance_period', true)
            ->orderBy('start_date', 'asc')
            ->get();

        $mapping = [
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday',
        ];

        $hasPeriods = $attendancePeriods->isNotEmpty();

        while ($count < $daysNeeded) {
            $dayOfWeek = $currentDate->format('l');
            $dateStr = $currentDate->toDateString();

            $isValid = false;
            if (in_array($dayOfWeek, $this->activeDays)) {
                if ($hasPeriods) {
                    $period = $attendancePeriods->first(function ($p) use ($dateStr) {
                        return $p->start_date->format('Y-m-d') <= $dateStr && $p->end_date->format('Y-m-d') >= $dateStr;
                    });
                    if ($period) {
                        $weekdays = $period->weekdays ?? [];
                        $periodWeekdays = array_map(fn ($wd) => $mapping[$wd], $weekdays);
                        if (in_array($dayOfWeek, $periodWeekdays)) {
                            $isValid = true;
                        }
                    } else {
                        $isValid = true; // schedule outside periods if not mapped
                    }
                } else {
                    $isValid = true;
                }
            }

            if ($isValid) {
                // Calculate Hifz Range for this day
                $hifzFrom = $this->hifzStart + ($count * $this->hifzRate);
                $hifzTo = min($this->hifzEnd, $hifzFrom + $this->hifzRate - 1);

                if ($hifzFrom > $this->hifzEnd) {
                    $hifzFrom = null;
                    $hifzTo = null;
                }

                // Calculate Review Range for this day
                $reviewFrom = null;
                $reviewTo = null;

                if ($this->hasReview) {
                    $reviewFrom = $this->reviewStart + ($count * $this->reviewRate);
                    $reviewTo = min($this->reviewEnd, $reviewFrom + $this->reviewRate - 1);

                    if ($reviewFrom > $this->reviewEnd) {
                        $reviewFrom = null;
                        $reviewTo = null;
                    }
                }

                $this->planDays[] = [
                    'date' => $currentDate->toDateString(),
                    'day_name' => $this->translateDay($dayOfWeek),
                    'from_verse_number' => $hifzFrom,
                    'to_verse_number' => $hifzTo,
                    'review_from_verse_number' => $reviewFrom,
                    'review_to_verse_number' => $reviewTo,
                ];
                $count++;
            }
            $currentDate->addDay();
        }

        $this->isGenerated = true;
    }

    public function resetPlan(): void
    {
        $this->isGenerated = false;
        $this->planDays = [];
    }

    public function savePlan(): void
    {
        $this->validate([
            'studentId' => 'required|exists:students,id',
            'odeId' => 'required|exists:odes,id',
            'startDate' => 'required|date',
        ]);

        if (empty($this->planDays)) {
            $this->addError('planDays', 'لا توجد أيام خطة للحفظ.');

            return;
        }

        // Deactivate existing active plans for this student and this ode
        StudentOdePlan::where('student_id', $this->studentId)
            ->where('ode_id', $this->odeId)
            ->where('status', 'active')
            ->update(['status' => 'completed']);

        // Create new plan
        $plan = StudentOdePlan::create([
            'student_id' => $this->studentId,
            'ode_id' => $this->odeId,
            'start_date' => $this->startDate,
            'status' => 'active',
            'created_by_role' => $this->userRole,
        ]);

        // Create plan days
        foreach ($this->planDays as $day) {
            StudentOdePlanDay::create([
                'student_ode_plan_id' => $plan->id,
                'date' => $day['date'],
                'day_name' => $day['day_name'],
                'from_verse_number' => $day['from_verse_number'],
                'to_verse_number' => $day['to_verse_number'],
                'review_from_verse_number' => $day['review_from_verse_number'],
                'review_to_verse_number' => $day['review_to_verse_number'],
            ]);
        }

        Flux::toast('تم حفظ خطة المنظومة للطالب بنجاح', variant: 'success');

        // Redirect back to circular management/dashboard or list
        if ($this->userRole === 'supervisor') {
            $this->redirectRoute('supervisor.students');
        } else {
            $this->redirectRoute('teacher.students');
        }
    }

    private function translateDay(string $day): string
    {
        $days = [
            'Sunday' => 'الأحد',
            'Monday' => 'الاثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
        ];

        return $days[$day] ?? $day;
    }

    private function getSupervisorCircleIds(): array
    {
        $supervisor = auth()->guard('supervisor')->user();
        if (! $supervisor) {
            return [];
        }

        return Circle::whereIn('stage_id', $supervisor->stages()->pluck('stages.id'))->pluck('id')->toArray();
    }

    public function render()
    {
        // Get list of odes
        $odes = Ode::orderBy('name')->get();

        // Get list of students based on user role
        $students = [];
        if ($this->userRole === 'teacher') {
            $teacher = auth()->guard('teacher')->user();
            $circleIds = $teacher->circles->pluck('id');
            $students = Student::whereIn('circle_id', $circleIds)->orderBy('name')->get();
        } else {
            $circleIds = $this->getSupervisorCircleIds();
            $students = Student::whereIn('circle_id', $circleIds)->orderBy('name')->get();
        }

        return view('livewire.shared.ode-plan-creator', [
            'odes' => $odes,
            'students' => $students,
        ]);
    }
}
