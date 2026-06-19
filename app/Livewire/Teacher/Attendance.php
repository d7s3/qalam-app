<?php

namespace App\Livewire\Teacher;

use App\Models\Attendance as AttendanceModel;
use App\Models\GamificationTransaction;
use App\Models\Setting;
use App\Models\Student;
use App\Services\GamificationService;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Attendance extends Component
{
    public $circles = [];

    public ?int $selectedCircle = null;

    #[Url]
    public string $date = '';

    public $students;

    /** @var array<int, string> student_id => status */
    public array $records = [];

    /** Ordered student IDs — used by Alpine for navigation */
    public array $studentOrder = [];

    public function mount(): void
    {

        $this->students = collect();
        if (empty($this->date)) {
            $this->date = now()->format('Y-m-d');
        }

        $teacher = auth()->guard('teacher')->user();
        $this->circles = $teacher->circles()->get();

        if ($this->circles->count() === 1) {
            $this->selectedCircle = $this->circles->first()->id;
            $this->loadStudents();
        }

    }

    public function updatedSelectedCircle(): void
    {
        $this->loadStudents();
    }

    public function updatedDate(): void
    {
        $this->loadStudents();
    }

    #[On('student-list-updated')]
    public function loadStudents(): void
    {
        if (! $this->selectedCircle) {
            $this->students = collect();
            $this->records = [];
            $this->studentOrder = [];
            $this->dispatch('studentsLoaded');

            return;
        }

        $studentsQuery = Student::where('circle_id', $this->selectedCircle)
            ->where('is_approved', true)
            ->where(function ($query) {
                $query->whereNull('joined_at')
                    ->orWhere('joined_at', '<=', $this->date);
            })
            ->with([
                'circle',
                'statusHistories' => function ($query) {
                    $query->where('start_date', '<=', $this->date)->orderBy('start_date', 'desc');
                },
            ])
            ->orderBy('name')
            ->get();

        $this->students = $studentsQuery->filter(function ($student) {
            $history = $student->statusHistories->first();
            $statusOnDate = $history ? $history->status : $student->status;

            return in_array($statusOnDate, ['active', 'registering']);
        })->values();

        $existing = AttendanceModel::where('circle_id', $this->selectedCircle)
            ->whereDate('date', $this->date)
            ->pluck('status', 'student_id')
            ->toArray();

        $this->records = [];
        foreach ($this->students as $student) {
            $this->records[$student->id] = $existing[$student->id] ?? '';
        }

        $this->studentOrder = $this->students->pluck('id')->toArray();

        // Tell Alpine to reset navigation state
        $this->dispatch('studentsLoaded');
    }

    /**
     * Wizard mode: save status. Alpine handles navigation immediately without waiting.
     */
    public function markStatus(int $studentId, string $status): void
    {
        if (! in_array($status, ['present', 'absent', 'late', 'excused'])) {
            return;
        }

        $this->records[$studentId] = $status;
        $this->saveRecord($studentId, $status);
        $this->dispatch('attendance-updated');
    }

    /**
     * List mode: save status and re-render so WhatsApp links reflect new state.
     */
    public function updateStatus(int $studentId, string $status): void
    {
        if (! in_array($status, ['present', 'absent', 'late', 'excused'])) {
            return;
        }

        $this->records[$studentId] = $status;
        $this->saveRecord($studentId, $status);
        $this->dispatch('attendance-updated');
    }

    public function markAllPresent(): void
    {
        $teacher = auth()->guard('teacher')->user();

        foreach ($this->students as $student) {
            if (AttendanceModel::whereDate('date', $this->date)->where('student_id', $student->id)->exists()) {
                continue;
            }
            $this->records[$student->id] = 'present';

            $attendance = AttendanceModel::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'date' => $this->date,
                ],
                [
                    'teacher_id' => $teacher->id,
                    'circle_id' => $this->selectedCircle,
                    'status' => 'present',
                ]
            );

            GamificationService::syncStudentAttendanceXP($attendance);
        }

        $this->isComplete = true;
        $this->dispatch('attendance-updated');
        Flux::toast('تم تسجيل حضور جميع الطلاب بنجاح', variant: 'success');
    }

    public function clearDayAttendance(): void
    {
        if (! $this->selectedCircle || empty($this->date)) {
            return;
        }

        $attendanceIds = AttendanceModel::where('circle_id', $this->selectedCircle)
            ->whereDate('date', $this->date)
            ->pluck('id')
            ->toArray();

        AttendanceModel::whereIn('id', $attendanceIds)->delete();

        GamificationTransaction::where('reference_type', AttendanceModel::class)
            ->whereIn('reference_id', $attendanceIds)
            ->delete();

        foreach ($this->students as $student) {
            $this->records[$student->id] = '';

            $activeLeaderboards = GamificationService::getActiveLeaderboards($student, $this->date);
            foreach ($activeLeaderboards as $lb) {
                GamificationService::recalculateStudentState($student->id, $lb->id);
            }
        }

        $this->dispatch('attendance-cleared');
        Flux::toast('تم حذف جميع بيانات التحضير لهذا اليوم', variant: 'success');
    }

    private function saveRecord(int $studentId, string $status): void
    {
        $teacher = auth()->guard('teacher')->user();

        $existing = AttendanceModel::where('student_id', $studentId)
            ->whereDate('date', $this->date)
            ->first();

        if ($existing) {
            $existing->update([
                'teacher_id' => $teacher->id,
                'circle_id' => $this->selectedCircle,
                'status' => $status,
            ]);
        } else {
            $existing = AttendanceModel::create([
                'student_id' => $studentId,
                'date' => $this->date,
                'teacher_id' => $teacher->id,
                'circle_id' => $this->selectedCircle,
                'status' => $status,
            ]);
        }

        GamificationService::syncStudentAttendanceXP($existing);
    }

    public function getWhatsAppMessage(Student $student, string $status = 'absent'): string
    {
        $hijriDate = $this->getHijriDate();

        $count = $status === 'late'
            ? $student->getLatenessInPeriodCount($this->date)
            : $student->getAbsencesInPeriodCount($this->date);

        $limitName = $status === 'late' ? 'lateness_limit' : 'absence_limit';
        $limit = (int) Setting::getVal($limitName, $status === 'late' ? 5 : 3);
        $statusText = $status === 'late' ? 'متأخر' : 'غائب';

        if ($statusText == 'متأخر') {
            $statusText = 'حضر الحلقة متأخراً';
        }
        $ordinals = [
            1 => 'الأولى',
            2 => 'الثانية',
            3 => 'الثالثة',
            4 => 'الرابعة',
            5 => 'الخامسة',
            6 => 'السادسة',
            7 => 'السابعة',
            8 => 'الثامنة',
            9 => 'التاسعة',
            10 => 'العاشرة',
        ];

        $ordinal = $ordinals[$count] ?? $count;

        $msg = "السلام عليكم ورحمة الله وبركاته\nنود إشعاركم بأن الطالب {$student->name} {$statusText} وذلك في اليوم {$hijriDate} وهذه للمرة {$ordinal}";

        if ($count >= $limit) {
            $msg .= " ويكون بذلك `تجاوز حد {$limit} ايام وسيتم إحالته للإدارة`.";
        } else {
            $statusText = $status === 'late' ? 'تأخر' : 'غياب';

            $msg .= " \n`وفي حال تم تسجيل {$limit} حالات {$statusText} على الطالب بلا عذر فسيتم إحالته للإدارة`";
        }

        return $msg;
    }

    private function getHijriDate(): string
    {
        $formatter = new \IntlDateFormatter(
            'ar_SA@calendar=islamic-umalqura',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            'Asia/Riyadh',
            \IntlDateFormatter::TRADITIONAL,
            'd MMMM yyyy'
        );

        return $formatter->format(strtotime($this->date));
    }

    public function render()
    {
        return view('livewire.teacher.attendance');
    }
}
