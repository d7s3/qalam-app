<?php

namespace App\Livewire\Shared;

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\Hadith;
use App\Models\HadithLine;
use App\Models\Student;
use App\Models\StudentHadithPlan;
use App\Models\StudentHadithPlanDay;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Component;

class HadithPlanCreator extends Component
{
    public $userRole; // 'supervisor' or 'teacher'

    // Form inputs
    public ?int $studentId = null;

    public ?int $hadithId = null;

    public string $startDate = '';

    public array $activeDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday'];

    // Hifz configuration
    public int $hifzStart = 1;

    public int $hifzEnd = 1;

    public int $hifzRate = 2;

    // Review configuration (optional)
    public bool $hasReview = false;

    public int $reviewStart = 1;

    public int $reviewEnd = 1;

    public int $reviewRate = 5;

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

    public function updatedHadithId($value): void
    {
        if ($value) {
            $maxLine = HadithLine::where('hadith_id', $value)->max('line_number') ?: 1;
            $this->hifzStart = 1;
            $this->hifzEnd = $maxLine;
            $this->reviewStart = 1;
            $this->reviewEnd = $maxLine;
        }
    }

    public function generatePreview(): void
    {
        $this->validate([
            'studentId' => 'required|exists:students,id',
            'hadithId' => 'required|exists:hadiths,id',
            'startDate' => 'required|date',
            'activeDays' => 'required|array|min:1',
            'hifzStart' => 'required|integer|min:1',
            'hifzEnd' => 'required|integer|gte:hifzStart',
            'hifzRate' => 'required|integer|min:1',
            'reviewStart' => 'required_if:hasReview,true|nullable|integer|min:1',
            'reviewEnd' => 'required_if:hasReview,true|nullable|integer|gte:reviewStart',
            'reviewRate' => 'required_if:hasReview,true|nullable|integer|min:1',
        ]);

        $maxLine = HadithLine::where('hadith_id', $this->hadithId)->max('line_number') ?: 1;

        if ($this->hifzEnd > $maxLine) {
            $this->addError('hifzEnd', "نهاية الحفظ لا يمكن أن تتجاوز عدد أسطر الحديث وهو {$maxLine} أسطر.");

            return;
        }

        if ($this->hasReview && $this->reviewEnd > $maxLine) {
            $this->addError('reviewEnd', "نهاية المراجعة لا يمكن أن تتجاوز عدد أسطر الحديث وهو {$maxLine} أسطر.");

            return;
        }

        // Calculate total days needed
        $hifzTotalLines = $this->hifzEnd - $this->hifzStart + 1;
        $hifzDays = (int) ceil($hifzTotalLines / $this->hifzRate);

        $daysNeeded = $hifzDays;

        if ($this->hasReview) {
            $reviewTotalLines = $this->reviewEnd - $this->reviewStart + 1;
            $reviewDays = (int) ceil($reviewTotalLines / $this->reviewRate);
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
                        $isValid = true;
                    }
                } else {
                    $isValid = true;
                }
            }

            if ($isValid) {
                // Calculate Hifz Range
                $hifzFrom = $this->hifzStart + ($count * $this->hifzRate);
                $hifzTo = min($this->hifzEnd, $hifzFrom + $this->hifzRate - 1);

                if ($hifzFrom > $this->hifzEnd) {
                    $hifzFrom = null;
                    $hifzTo = null;
                }

                // Calculate Review Range
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
                    'from_line_number' => $hifzFrom,
                    'to_line_number' => $hifzTo,
                    'review_from_line_number' => $reviewFrom,
                    'review_to_line_number' => $reviewTo,
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
            'hadithId' => 'required|exists:hadiths,id',
            'startDate' => 'required|date',
        ]);

        if (empty($this->planDays)) {
            $this->addError('planDays', 'لا توجد أيام خطة للحفظ.');

            return;
        }

        // Deactivate existing active plans for this student and this hadith
        StudentHadithPlan::where('student_id', $this->studentId)
            ->where('hadith_id', $this->hadithId)
            ->where('status', 'active')
            ->update(['status' => 'completed']);

        // Create new plan
        $plan = StudentHadithPlan::create([
            'student_id' => $this->studentId,
            'hadith_id' => $this->hadithId,
            'start_date' => $this->startDate,
            'status' => 'active',
            'created_by_role' => $this->userRole,
        ]);

        // Create plan days
        foreach ($this->planDays as $day) {
            StudentHadithPlanDay::create([
                'student_hadith_plan_id' => $plan->id,
                'date' => $day['date'],
                'day_name' => $day['day_name'],
                'from_line_number' => $day['from_line_number'],
                'to_line_number' => $day['to_line_number'],
                'review_from_line_number' => $day['review_from_line_number'],
                'review_to_line_number' => $day['review_to_line_number'],
            ]);
        }

        Flux::toast('تم حفظ خطة الحديث للطالب بنجاح', variant: 'success');

        if ($this->userRole === 'supervisor') {
            $this->redirectRoute('supervisor.hadiths');
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
        $hadiths = Hadith::orderBy('name')->get();

        $students = [];
        if ($this->userRole === 'teacher') {
            $teacher = auth()->guard('teacher')->user();
            $circleIds = $teacher->circles->pluck('id');
            $students = Student::whereIn('circle_id', $circleIds)->orderBy('name')->get();
        } else {
            $circleIds = $this->getSupervisorCircleIds();
            $students = Student::whereIn('circle_id', $circleIds)->orderBy('name')->get();
        }

        return view('livewire.shared.hadith-plan-creator', [
            'hadiths' => $hadiths,
            'students' => $students,
        ]);
    }
}
