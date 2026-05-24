<?php

use Livewire\Component;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;
use Livewire\Attributes\On;

new class extends Component {
    public $navigatedDays = []; // [student_id => day_id]
    public $selectedPlans = []; // [student_id => plan_id]

    // Reservation session properties
    public $showSessionModal = false;
    public $sessionStartTime = '16:00';
    public $sessionEndTime = '18:00';
    public $sessionStartDate;
    public $sessionEndDate;
    public $sessionDaysOfWeek = [0, 1, 2, 3, 4, 5, 6];

    public $gradedAtDate;
    public $refreshToggle = false;

    public function mount()
    {
        $this->gradedAtDate = now()->format('Y-m-d');
    }

    #[On('attendance-updated')]
    #[On('attendance-cleared')]
    #[On('plan-created')]
    public function refreshData()
    {
        $this->refreshToggle = ! $this->refreshToggle;
        Flux::toast('تم تحديث قائمة التسميع', variant: 'success');
    }

    public function with()
    {
        $teacher = Auth::guard('teacher')->user();
        $circleIds = $teacher->circles()->pluck('id');

        $students = Student::whereIn('circle_id', $circleIds)->get();

        $todayStr = \Carbon\Carbon::today()->format('Y-m-d');

        // Fetch active plans (or selected plans) for these students
        $activePlans = [];
        $studentPlansList = StudentPlan::whereIn('student_id', $students->pluck('id'))
            ->latest()
            ->get()
            ->groupBy('student_id');

        foreach ($students as $student) {
            $sPlans = $studentPlansList[$student->id] ?? collect();
            if ($sPlans->isEmpty()) continue;

            $chosenPlanId = $this->selectedPlans[$student->id] ?? null;
            $activePlan = $chosenPlanId 
                ? $sPlans->firstWhere('id', $chosenPlanId) 
                : $sPlans->firstWhere('status', 'active');
            
            if (!$activePlan) $activePlan = $sPlans->first(); // fallback to latest
            
            $activePlans[$student->id] = $activePlan;
        }

        // We need all today's plan days for the active plans to check the colors
        $planIdToStudentId = collect($activePlans)->pluck('student_id', 'id')->toArray();
        $todaysPlanDays = StudentPlanDay::whereIn('student_plan_id', collect($activePlans)->pluck('id'))
            ->whereDate('date', $todayStr)
            ->get()
            ->groupBy(function ($day) use ($planIdToStudentId) {
                return $planIdToStudentId[$day->student_plan_id] ?? null;
            });

        $todayAttendances = \App\Models\Attendance::whereIn('student_id', $students->pluck('id'))
            ->whereDate('date', $todayStr)
            ->get()
            ->keyBy('student_id');

        $allSessions = \App\Models\TurnReservationSession::where('teacher_id', $teacher->id)->get();
        $activeSession = null;
        foreach ($allSessions as $session) {
            if ($session->isActiveToday()) {
                $activeSession = $session;
                break;
            }
        }

        $reservations = collect();
        if ($activeSession) {
            $reservations = \App\Models\TurnReservation::where('turn_reservation_session_id', $activeSession->id)
                ->whereDate('date', $todayStr)
                ->get()
                ->keyBy('student_id');
        }

        $studentsWithPlansPresent = [];
        $studentsWithPlansAbsent = [];
        $studentsWithoutPlans = [];

        foreach ($students as $student) {
            if (isset($activePlans[$student->id])) {
                $color = 'red';

                if (isset($todaysPlanDays[$student->id])) {
                    $days = $todaysPlanDays[$student->id];
                    $hasTasks = false;
                    $totalRequired = 0;
                    $completedCount = 0;
                    $hasAnyAchievement = false;

                    foreach ($days as $day) {
                        if ($day->from_ayah_id) {
                            $hasTasks = true;
                            $totalRequired++;
                            if ($day->hifz_achievement !== null) {
                                $completedCount++;
                                $hasAnyAchievement = true;
                            }
                        }
                        if ($day->review_from_ayah_id) {
                            $hasTasks = true;
                            $totalRequired++;
                            if ($day->review_achievement !== null) {
                                $completedCount++;
                                $hasAnyAchievement = true;
                            }
                        }
                    }

                    if (!$hasTasks) {
                        $color = 'zinc'; // No actual tasks today
                    } elseif ($totalRequired > 0 && $completedCount === $totalRequired) {
                        $color = 'emerald'; // Full
                    } elseif ($hasAnyAchievement) {
                        $color = 'blue'; // Partial
                    } else {
                        $color = 'rose'; // None
                    }
                } else {
                    $color = 'zinc'; // No plan day for today
                }

                $student->tasmeeh_color = $color;
                $student->turn_number = isset($reservations[$student->id]) ? $reservations[$student->id]->turn_number : 9999;

                $attendanceStatus = isset($todayAttendances[$student->id]) ? $todayAttendances[$student->id]->status : 'present';
                if (in_array($attendanceStatus, ['absent', 'excused'])) {
                    $studentsWithPlansAbsent[] = $student;
                } else {
                    $studentsWithPlansPresent[] = $student;
                }
            } else {
                $studentsWithoutPlans[] = $student;
            }
        }

        // Determine the display day for each active plan
        $activePlanIds = collect($activePlans)->pluck('id');
        $allPlanDays = StudentPlanDay::whereIn('student_plan_id', $activePlanIds)
            ->orderBy('date', 'asc')
            ->get(['id', 'student_plan_id', 'date', 'hifz_achievement', 'review_achievement'])
            ->groupBy('student_plan_id');

        $defaultDayIds = [];
        foreach ($activePlans as $studentId => $plan) {
            if (isset($this->navigatedDays[$studentId])) {
                $defaultDayIds[] = $this->navigatedDays[$studentId];
            } else {
                $days = $allPlanDays->get($plan->id) ?? collect();
                
                $oldestIncomplete = $days->first(function ($day) use ($plan) {
                    if ($plan->plan_type === 'hifz') {
                        return is_null($day->hifz_achievement);
                    } elseif ($plan->plan_type === 'review') {
                        return is_null($day->review_achievement);
                    } else {
                        return is_null($day->hifz_achievement) || is_null($day->review_achievement);
                    }
                });

                if ($oldestIncomplete) {
                    $defaultDayIds[] = $oldestIncomplete->id;
                } else {
                    $lastDay = $days->last();
                    if ($lastDay) {
                        $defaultDayIds[] = $lastDay->id;
                    }
                }
            }
        }

        $activeDays = StudentPlanDay::with(['fromAyah.surah', 'toAyah.surah', 'reviewFromAyah.surah', 'reviewToAyah.surah', 'plan'])
            ->whereIn('id', $defaultDayIds)
            ->get()
            ->keyBy(function($day) { return $day->plan->student_id; });

        $hasNext = [];
        $hasPrev = [];
        foreach ($activeDays as $studentId => $day) {
            $days = $allPlanDays->get($day->student_plan_id) ?? collect();
            $dayDate = \Carbon\Carbon::parse($day->date)->format('Y-m-d');

            $hasNext[$studentId] = $days->contains(function ($d) use ($dayDate) {
                return \Carbon\Carbon::parse($d->date)->format('Y-m-d') > $dayDate;
            });
            $hasPrev[$studentId] = $days->contains(function ($d) use ($dayDate) {
                return \Carbon\Carbon::parse($d->date)->format('Y-m-d') < $dayDate;
            });
        }

        $studentsWithPlansPresent = collect($studentsWithPlansPresent)->sortBy('turn_number')->values();

        return [
            'studentsWithPlansPresent' => $studentsWithPlansPresent,
            'studentsWithPlansAbsent' => collect($studentsWithPlansAbsent),
            'studentsWithoutPlans' => collect($studentsWithoutPlans),
            'studentPlansList' => $studentPlansList,
            'activePlans' => $activePlans,
            'activeDays' => $activeDays,
            'hasNext' => $hasNext,
            'hasPrev' => $hasPrev,
            'activeSession' => $activeSession,
        ];
    }

    public function selectPlan($studentId, $planId)
    {
        $this->selectedPlans[$studentId] = $planId;
        unset($this->navigatedDays[$studentId]); // Reset to default day for new plan
    }

    public function previousDay($studentId, $currentDayId)
    {
        $currentDay = StudentPlanDay::find($currentDayId);
        if ($currentDay) {
            $prev = StudentPlanDay::where('student_plan_id', $currentDay->student_plan_id)
                ->whereDate('date', '<', \Carbon\Carbon::parse($currentDay->date)->format('Y-m-d'))
                ->orderBy('date', 'desc')
                ->first();
            if ($prev) {
                $this->navigatedDays[$studentId] = $prev->id;
            }
        }
    }

    public function nextDay($studentId, $currentDayId)
    {
        $currentDay = StudentPlanDay::find($currentDayId);
        if ($currentDay) {
            $next = StudentPlanDay::where('student_plan_id', $currentDay->student_plan_id)
                ->whereDate('date', '>', \Carbon\Carbon::parse($currentDay->date)->format('Y-m-d'))
                ->orderBy('date', 'asc')
                ->first();
            if ($next) {
                $this->navigatedDays[$studentId] = $next->id;
            }
        }
    }

    public function saveAchievement($dayId, $type, $value)
    {
        $updateData = [];
        $gradeTime = null;
        if ($this->gradedAtDate) {
            if ($this->gradedAtDate === now()->format('Y-m-d')) {
                $gradeTime = now();
            } else {
                $gradeTime = \Carbon\Carbon::parse($this->gradedAtDate)->setHour(12)->setMinute(0);
            }
        }

        if ($type === 'hifz') {
            $updateData['hifz_achievement'] = $value;
            $updateData['hifz_graded_at'] = $value !== null ? $gradeTime : null;
        } elseif ($type === 'review') {
            $updateData['review_achievement'] = $value;
            $updateData['review_graded_at'] = $value !== null ? $gradeTime : null;
        }

        StudentPlanDay::where('id', $dayId)->update($updateData);
        
        // Lock the navigated day so the UI doesn't suddenly jump to the next incomplete day
        // which makes it look like the button reverted to an ungraded state
        $day = StudentPlanDay::with('plan')->find($dayId);
        if ($day) {
            $this->navigatedDays[$day->plan->student_id] = $dayId;
        }

        Flux::toast('تم حفظ التقييم', variant: 'success');
    }

    public function openSessionModal()
    {
        $teacher = Auth::guard('teacher')->user();
        $session = \App\Models\TurnReservationSession::where('teacher_id', $teacher->id)->first();

        if ($session) {
            $this->sessionStartTime = \Carbon\Carbon::parse($session->start_time)->format('H:i');
            $this->sessionEndTime = \Carbon\Carbon::parse($session->end_time)->format('H:i');
            $this->sessionStartDate = \Carbon\Carbon::parse($session->start_date)->format('Y-m-d');
            $this->sessionEndDate = \Carbon\Carbon::parse($session->end_date)->format('Y-m-d');
            $this->sessionDaysOfWeek = $session->days_of_week ?? [0, 1, 2, 3, 4, 5, 6];
        } else {
            $this->sessionStartTime = '16:00';
            $this->sessionEndTime = '18:00';
            $this->sessionStartDate = \Carbon\Carbon::now('Asia/Riyadh')->format('Y-m-d');
            $this->sessionEndDate = \Carbon\Carbon::now('Asia/Riyadh')->addMonths(1)->format('Y-m-d');
            $this->sessionDaysOfWeek = [0, 1, 2, 3, 4]; // Default to Sunday-Thursday
        }

        $this->showSessionModal = true;
    }

    public function saveSession()
    {
        $this->validate([
            'sessionStartTime' => 'required',
            'sessionEndTime' => 'required',
            'sessionStartDate' => 'required|date',
            'sessionEndDate' => 'required|date|after_or_equal:sessionStartDate',
            'sessionDaysOfWeek' => 'required|array|min:1',
        ]);

        $teacher = Auth::guard('teacher')->user();

        // Convert string arrays to integers
        $days = array_map('intval', $this->sessionDaysOfWeek);

        $session = \App\Models\TurnReservationSession::where('teacher_id', $teacher->id)->first();

        if ($session) {
            $session->update([
                'start_time' => $this->sessionStartTime,
                'end_time' => $this->sessionEndTime,
                'start_date' => $this->sessionStartDate,
                'end_date' => $this->sessionEndDate,
                'days_of_week' => $days,
            ]);
        } else {
            \App\Models\TurnReservationSession::create([
                'teacher_id' => $teacher->id,
                'start_time' => $this->sessionStartTime,
                'end_time' => $this->sessionEndTime,
                'start_date' => $this->sessionStartDate,
                'end_date' => $this->sessionEndDate,
                'days_of_week' => $days,
            ]);
        }

        $this->showSessionModal = false;
        Flux::toast('تم حفظ إعدادات حجز الأدوار بنجاح', variant: 'success');
    }

};
?>

{{--
Alpine state:
activeStudentId — tracks which student is being viewed entirely on the client
hifz/review — local state per day card for instant visual feedback
--}}
<div class="space-y-6" x-data="{
        activeStudentId: null,
     }">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('التسميع والمتابعة') }}</flux:heading>
            <flux:subheading>{{ __('اختر الطالب ثم الخطة لعرض المهام المطلوبة وتقييم الإنجاز يومياً.') }}
            </flux:subheading>
        </div>
        <div class="flex flex-col md:flex-row md:items-center gap-2">
            @if($activeSession)
                <flux:badge color="emerald" variant="pill" icon="clock">
                    {{ __('حجز الأدوار مفعل') }}
                    ({{ \Carbon\Carbon::parse($activeSession->start_time)->format('g:i A') }} -
                    {{ \Carbon\Carbon::parse($activeSession->end_time)->format('g:i A') }})
                </flux:badge>
            @endif
            <flux:button wire:click="openSessionModal" icon="ticket" variant="outline" class="shrink-0">
                {{ __('إعدادات حجز الأدوار') }}
            </flux:button>
        </div>
    </div>

    {{-- Selects & Student List Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        <!-- Students List Sidebar -->
        <div x-data="{ 
                openSection: 1, 
                selectStudent(id) {
                    this.activeStudentId = id;
                    setTimeout(() => {
                        const el = document.getElementById('grading-area');
                        if(el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                }
             }"
            class="lg:col-span-1 flex flex-col gap-4 bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 h-fit max-h-[calc(100vh-140px)] overflow-y-auto lg:sticky lg:top-24 scrollbar-thin">

            <!-- Section 1a: With Plans (Present / Late) -->
            <div
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-scroll shadow-sm">
                <button @click="openSection = openSection === 1 ? 0 : 1"
                    class="w-full flex items-center justify-between p-3 bg-zinc-50/50 dark:bg-zinc-800/30 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" variant="micro" class="text-emerald-500" />
                        <span class="font-bold text-sm text-zinc-700 dark:text-zinc-300">{{ __('حاضر / متأخر') }}</span>
                        <span
                            class="text-xs bg-zinc-200 dark:bg-zinc-700 px-1.5 py-0.5 rounded-md text-zinc-600 dark:text-zinc-400">{{ count($studentsWithPlansPresent) }}</span>
                    </div>
                    <flux:icon icon="chevron-down" class="size-4 text-zinc-400 transition-transform"
                        x-bind:class="openSection === 1 ? 'rotate-180' : ''" />
                </button>
                <div x-show="openSection === 1"
                    class="p-2 space-y-1.5 border-t border-zinc-100 dark:border-zinc-800 max-h-[50vh] overflow-y-auto scrollbar-thin">
                    @forelse($studentsWithPlansPresent as $student)
                        <button wire:key="present-{{ $student->id }}-{{ $refreshToggle ? '1' : '0' }}" @click="selectStudent({{ $student->id }})"
                            class="w-full flex items-center justify-between p-2.5 rounded-xl border text-right transition-colors"
                            :class="activeStudentId === {{ $student->id }} ? 'bg-indigo-50 border-indigo-200 dark:bg-indigo-900/40 dark:border-indigo-800' : 'bg-white dark:bg-zinc-800 border-transparent hover:border-zinc-200 dark:hover:border-zinc-700'">
                            <div class="flex items-center gap-3">
                                <div
                                    class="size-2.5 rounded-full bg-{{ $student->tasmeeh_color }}-500 shadow-sm shadow-{{ $student->tasmeeh_color }}-500/30 shrink-0">
                                </div>
                                <span
                                    class="font-medium text-sm truncate"
                                    :class="activeStudentId === {{ $student->id }} ? 'text-indigo-700 dark:text-indigo-400' : 'text-zinc-700 dark:text-zinc-300'">{{ $student->name }}</span>
                            </div>
                            @if($student->turn_number !== 9999)
                                <span
                                    class="shrink-0 flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-bold rounded-md"
                                    :class="activeStudentId === {{ $student->id }} ? 'bg-indigo-200 text-indigo-800 dark:bg-indigo-800 dark:text-indigo-200' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'">
                                    {{ $student->turn_number }}
                                </span>
                            @endif
                        </button>
                    @empty
                        <div class="text-xs text-center text-zinc-400 py-3">{{ __('لا يوجد طلاب حالياً.') }}</div>
                    @endforelse
                </div>
            </div>

            <!-- Section 1b: With Plans (Absent / Excused) -->
            @if($studentsWithPlansAbsent->isNotEmpty())
                <div
                    class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm shrink-0">
                    <button @click="openSection = openSection === 2 ? 0 : 2"
                        class="w-full flex items-center justify-between p-3 bg-zinc-50/50 dark:bg-zinc-800/30 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="x-circle" variant="micro" class="text-rose-500" />
                            <span class="font-bold text-sm text-zinc-700 dark:text-zinc-300">{{ __('غائب / معتذر') }}</span>
                            <span
                                class="text-xs bg-zinc-200 dark:bg-zinc-700 px-1.5 py-0.5 rounded-md text-zinc-600 dark:text-zinc-400">{{ count($studentsWithPlansAbsent) }}</span>
                        </div>
                        <flux:icon icon="chevron-down" class="size-4 text-zinc-400 transition-transform"
                            x-bind:class="openSection === 2 ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="openSection === 2"
                        class="p-2 space-y-1.5 border-t border-zinc-100 dark:border-zinc-800 max-h-[50vh] overflow-y-auto scrollbar-thin">
                        @forelse($studentsWithPlansAbsent as $student)
                            <button wire:key="absent-{{ $student->id }}-{{ $refreshToggle ? '1' : '0' }}" @click="selectStudent({{ $student->id }})"
                                class="w-full flex items-center justify-between p-2.5 rounded-xl border text-right transition-colors"
                                :class="activeStudentId === {{ $student->id }} ? 'bg-indigo-50 border-indigo-200 dark:bg-indigo-900/40 dark:border-indigo-800' : 'bg-rose-50 dark:bg-rose-900/10 border-transparent hover:border-rose-200 dark:hover:border-rose-800/50 opacity-75 hover:opacity-100'">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="size-2.5 rounded-full bg-{{ $student->tasmeeh_color }}-500 shadow-sm shadow-{{ $student->tasmeeh_color }}-500/30 shrink-0">
                                    </div>
                                    <span
                                        class="font-medium text-sm truncate"
                                        :class="activeStudentId === {{ $student->id }} ? 'text-indigo-700 dark:text-indigo-400' : 'text-rose-700 dark:text-rose-400'">{{ $student->name }}</span>
                                </div>
                            </button>
                        @empty
                        @endforelse
                    </div>
                </div>
            @endif

            <!-- Section 1c: Without Plans -->
            @if($studentsWithoutPlans->isNotEmpty())
                <div
                    class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm shrink-0">
                    <button @click="openSection = openSection === 3 ? 0 : 3"
                        class="w-full flex items-center justify-between p-3 bg-zinc-50/50 dark:bg-zinc-800/30 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="document-minus" variant="micro" class="text-zinc-400" />
                            <span
                                class="font-bold text-sm text-zinc-700 dark:text-zinc-300">{{ __('غير المجدولين') }}</span>
                            <span
                                class="text-xs bg-zinc-200 dark:bg-zinc-700 px-1.5 py-0.5 rounded-md text-zinc-600 dark:text-zinc-400">{{ count($studentsWithoutPlans) }}</span>
                        </div>
                        <flux:icon icon="chevron-down" class="size-4 text-zinc-400 transition-transform"
                            x-bind:class="openSection === 3 ? 'rotate-180' : ''" />
                    </button>
                    <div x-show="openSection === 3"
                        class="p-2 space-y-1.5 border-t border-zinc-100 dark:border-zinc-800 max-h-[50vh] overflow-y-auto scrollbar-thin">
                        @forelse($studentsWithoutPlans as $student)
                            <div wire:key="noplan-{{ $student->id }}-{{ $refreshToggle ? '1' : '0' }}" class="flex items-center gap-2">
                                <button @click="selectStudent({{ $student->id }})"
                                    class="flex-1 flex items-center p-2.5 rounded-xl border text-right transition-colors"
                                    :class="activeStudentId === {{ $student->id }} ? 'bg-indigo-50 border-indigo-200 dark:bg-indigo-900/40 dark:border-indigo-800' : 'bg-zinc-100/50 dark:bg-zinc-800/30 border-transparent hover:border-zinc-200 dark:hover:border-zinc-700'">
                                    <span
                                        class="font-medium text-sm truncate"
                                        :class="activeStudentId === {{ $student->id }} ? 'text-indigo-700 dark:text-indigo-400' : 'text-zinc-500 dark:text-zinc-400'">{{ $student->name }}</span>
                                </button>
                                <a href="{{ route('teacher.plan-creator', ['studentId' => $student->id]) }}"
                                    class="shrink-0 p-2.5 text-emerald-600 hover:text-white bg-emerald-50 hover:bg-emerald-500 dark:text-emerald-400 dark:bg-emerald-900/20 dark:hover:bg-emerald-600 rounded-xl   s"
                                    title="{{ __('إنشاء خطة') }}">
                                    <flux:icon icon="plus" class="size-4" variant="mini" />
                                </a>
                            </div>
                        @empty
                        @endforelse
                    </div>
                </div>
            @endif

        </div>

        <!-- Main Content Area -->
        <div id="grading-area" class="lg:col-span-3 space-y-6 scroll-mt-6">
            <div x-show="!activeStudentId" class="flex flex-col items-center justify-center p-12 bg-zinc-50/50 dark:bg-zinc-900/50 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl text-center h-full min-h-[400px]">
                <flux:icon icon="user-group" class="size-16 text-zinc-300 dark:text-zinc-600 mb-4" />
                <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400 mb-2">{{ __('اختر طالباً للبدء') }}</flux:heading>
                <p class="text-zinc-400 dark:text-zinc-500 text-sm max-w-sm">
                    {{ __('قم باختيار أحد الطلاب من القائمة الجانبية لعرض خطته القرآنية والبدء بتقييم التسميع والمراجعة.') }}
                </p>
            </div>

            <!-- Global Grading Date Setting -->
            <div x-show="activeStudentId" x-cloak class="bg-white dark:bg-zinc-900 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm mb-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <flux:label>{{ __('تاريخ التقييم (الإنجاز الفعلي)') }}</flux:label>
                        <flux:description class="text-[11px] mt-0.5">{{ __('سيُستخدم هذا التاريخ لتسجيل إنجاز الطالب في المسابقات والتقارير.') }}</flux:description>
                    </div>
                    <div class="w-full md:w-64">
                        <livewire:teacher.hijri-datepicker wire:model.live="gradedAtDate" />
                    </div>
                </div>
            </div>

            @foreach($studentsWithPlansPresent->merge($studentsWithPlansAbsent)->merge($studentsWithoutPlans) as $student)
                <div x-show="activeStudentId === {{ $student->id }}" x-cloak>
                    @php
                        $sPlans = $studentPlansList[$student->id] ?? collect();
                        $activePlan = $activePlans[$student->id] ?? null;
                        $currentDay = $activeDays[$student->id] ?? null;
                        $sHasNext = $hasNext[$student->id] ?? false;
                        $sHasPrev = $hasPrev[$student->id] ?? false;
                    @endphp

                    @if($sPlans->isNotEmpty())
                        <div class="bg-white dark:bg-zinc-900 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm mb-4">
                            <flux:select wire:change="selectPlan({{ $student->id }}, $event.target.value)" label="{{ __('الخطة القرآنية') }}" placeholder="{{ __('اختر الخطة') }}">
                                @foreach($sPlans as $plan)
                                    <flux:select.option value="{{ $plan->id }}" :selected="$activePlan && $activePlan->id === $plan->id">
                                        @if($plan->plan_type === 'hifz')
                                            {{ __('حفظ (تبدأ من ' . $plan->start_date->format('Y/m/d') . ')') }}
                                        @elseif($plan->plan_type === 'review')
                                            {{ __('مراجعة (تبدأ من ' . $plan->start_date->format('Y/m/d') . ')') }}
                                        @else
                                            {{ __('حفظ ومراجعة (تبدأ من ' . $plan->start_date->format('Y/m/d') . ')') }}
                                        @endif
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        @if($currentDay)
                            <flux:card wire:key="day-card-{{ $currentDay->id }}" x-data="{ syncingTask: null }" class="mt-2 border-zinc-200 dark:border-zinc-700">

                                {{-- Day navigation --}}
                                <div class="flex items-center justify-between mb-8 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                                    <flux:button wire:click="previousDay({{ $student->id }}, {{ $currentDay->id }})" :disabled="!$sHasPrev" icon="chevron-right" variant="subtle" size="sm">
                                        {{ __('اليوم السابق') }}
                                    </flux:button>

                                    <div class="text-center">
                                        <div class="font-bold text-lg">{{ $currentDay->day_name }}</div>
                                        <div class="text-zinc-500 text-sm dir-ltr">{{ $currentDay->date->format('Y/m/d') }}</div>
                                    </div>

                                    <flux:button wire:click="nextDay({{ $student->id }}, {{ $currentDay->id }})" :disabled="!$sHasNext" icon-trailing="chevron-left" variant="subtle" size="sm">
                                        {{ __('اليوم التالي') }}
                                    </flux:button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    {{-- Hifz Section --}}
                                    @if($currentDay->plan->plan_type === 'hifz' || $currentDay->plan->plan_type === 'hifz_review')
                                        <div class="bg-indigo-50/50 dark:bg-indigo-500/5 rounded-xl border border-indigo-100 dark:border-indigo-500/20 p-5 space-y-5">
                                            <div>
                                                <flux:heading size="lg" class="text-indigo-600 dark:text-indigo-400 mb-2">{{ __('الحفظ') }}</flux:heading>
                                                <p class="text-zinc-700 dark:text-zinc-300 font-medium text-lg leading-relaxed">
                                                    {{ $currentDay->formatRange('hifz') ?? 'لا يوجد نص محدد' }}
                                                </p>
                                                @php
                                                    $hLinks = [];
                                                    $hFrom = $currentDay->fromAyah;
                                                    $hTo   = $currentDay->toAyah;
                                                    if ($hFrom && $hTo) {
                                                        if ($hFrom->surah_id === $hTo->surah_id) {
                                                            $hLinks[] = [
                                                                'name' => $hFrom->surah->name_arabic,
                                                                'url'  => 'https://quran.com/ar/' . $hFrom->surah->number . '/' . $hFrom->verse_number . '-' . $hTo->verse_number,
                                                            ];
                                                        } else {
                                                            $low  = min($hFrom->surah_id, $hTo->surah_id);
                                                            $high = max($hFrom->surah_id, $hTo->surah_id);
                                                            $direction = $hFrom->surah_id <= $hTo->surah_id ? 'asc' : 'desc';
                                                            $surahs = \App\Models\Surah::whereBetween('id', [$low, $high])->orderBy('id', $direction)->get();
                                                            foreach ($surahs as $s) {
                                                                $from = $s->id === $hFrom->surah_id ? $hFrom->verse_number : 1;
                                                                $to   = $s->id === $hTo->surah_id   ? $hTo->verse_number   : $s->verses_count;
                                                                $hLinks[] = [
                                                                    'name' => $s->name_arabic,
                                                                    'url'  => 'https://quran.com/ar/' . $s->number . '/' . $from . '-' . $to,
                                                                ];
                                                            }
                                                        }
                                                    } elseif ($hFrom) {
                                                        $hLinks[] = [
                                                            'name' => $hFrom->surah->name_arabic,
                                                            'url'  => 'https://quran.com/ar/' . $hFrom->surah->number . '/' . $hFrom->verse_number . '-' . $hFrom->surah->verses_count,
                                                        ];
                                                    }
                                                @endphp
                                                @if(count($hLinks) === 1)
                                                    <a href="{{ $hLinks[0]['url'] }}" target="_blank"
                                                        class="inline-flex items-center gap-1.5 mt-3 px-2.5 py-1 rounded-lg bg-indigo-100 dark:bg-indigo-500/20 text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-500/30 transition-colors">
                                                        <flux:icon icon="book-open" class="size-3.5" />
                                                        {{ __('افتح') }} {{ $hLinks[0]['name'] }}
                                                    </a>
                                                @elseif(count($hLinks) > 1)
                                                    <div x-data="{ open: false }" class="mt-3">
                                                        <button type="button" @click="open = !open"
                                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-100 dark:bg-indigo-500/20 text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-500/30 transition-colors">
                                                            <flux:icon icon="book-open" class="size-3.5" />
                                                            <span>{{ __('افتح الآيات في القرآن') }} ({{ count($hLinks) }})</span>
                                                            <flux:icon icon="chevron-down" class="size-3.5 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                                                        </button>
                                                        <div x-show="open" x-collapse class="flex flex-wrap gap-2 mt-2">
                                                            @foreach($hLinks as $link)
                                                                <a href="{{ $link['url'] }}" target="_blank"
                                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-100 dark:bg-indigo-500/20 text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-500/30 transition-colors">
                                                                    <flux:icon icon="book-open" class="size-3.5" />
                                                                    {{ $link['name'] }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            <flux:separator />

                                            <div>
                                                <flux:label class="mb-3 font-semibold">{{ __('تقييم الإنجاز (التسميع)') }}</flux:label>
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                    <button type="button" @click="syncingTask = 'hifz-3'; await $wire.saveAchievement({{ $currentDay->id }}, 'hifz', 3); syncingTask = null"
                                                        :disabled="syncingTask !== null"
                                                        class="p-3 rounded-xl border-2 transition-colors font-bold text-center disabled:opacity-50 disabled:cursor-wait"
                                                        :class="syncingTask === 'hifz-3' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->hifz_achievement === 3 ? 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-700 dark:text-green-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-green-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">ممتاز</button>

                                                    <button type="button" @click="syncingTask = 'hifz-2'; await $wire.saveAchievement({{ $currentDay->id }}, 'hifz', 2); syncingTask = null"
                                                        :disabled="syncingTask !== null"
                                                        class="p-3 rounded-xl border-2 transition-colors font-bold text-center disabled:opacity-50 disabled:cursor-wait"
                                                        :class="syncingTask === 'hifz-2' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->hifz_achievement === 2 ? 'border-blue-500 bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-blue-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">جيد</button>

                                                    <button type="button" @click="syncingTask = 'hifz-1'; await $wire.saveAchievement({{ $currentDay->id }}, 'hifz', 1); syncingTask = null"
                                                        :disabled="syncingTask !== null"
                                                        class="p-3 rounded-xl border-2 transition-colors font-bold text-center disabled:opacity-50 disabled:cursor-wait"
                                                        :class="syncingTask === 'hifz-1' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->hifz_achievement === 1 ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-amber-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">مقبول</button>

                                                    <button type="button" @click="syncingTask = 'hifz-null'; await $wire.saveAchievement({{ $currentDay->id }}, 'hifz', null); syncingTask = null"
                                                        :disabled="syncingTask !== null"
                                                        class="p-3 rounded-xl border-2 transition-colors font-bold text-center disabled:opacity-50 disabled:cursor-wait"
                                                        :class="syncingTask === 'hifz-null' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->hifz_achievement === null ? 'border-red-500 bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-red-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">لم يسمع</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Review Section --}}
                                    @if($currentDay->plan->plan_type === 'review' || $currentDay->plan->plan_type === 'hifz_review')
                                        <div class="bg-emerald-50/50 dark:bg-emerald-500/5 rounded-xl border border-emerald-100 dark:border-emerald-500/20 p-5 space-y-5">
                                            <div>
                                                <flux:heading size="lg" class="text-emerald-600 dark:text-emerald-400 mb-2">{{ __('المراجعة') }}</flux:heading>
                                                <p class="text-zinc-700 dark:text-zinc-300 font-medium text-lg leading-relaxed">
                                                    {{ $currentDay->formatRange('review') ?? 'لا يوجد نص محدد' }}
                                                </p>
                                                @php
                                                    $rLinks = [];
                                                    $rFrom = $currentDay->reviewFromAyah;
                                                    $rTo   = $currentDay->reviewToAyah;
                                                    if ($rFrom && $rTo) {
                                                        if ($rFrom->surah_id === $rTo->surah_id) {
                                                            $rLinks[] = [
                                                                'name' => $rFrom->surah->name_arabic,
                                                                'url'  => 'https://quran.com/ar/' . $rFrom->surah->number . '/' . $rFrom->verse_number . '-' . $rTo->verse_number,
                                                            ];
                                                        } else {
                                                            $low  = min($rFrom->surah_id, $rTo->surah_id);
                                                            $high = max($rFrom->surah_id, $rTo->surah_id);
                                                            $direction = $rFrom->surah_id <= $rTo->surah_id ? 'asc' : 'desc';
                                                            $surahs = \App\Models\Surah::whereBetween('id', [$low, $high])->orderBy('id', $direction)->get();
                                                            foreach ($surahs as $s) {
                                                                $from = $s->id === $rFrom->surah_id ? $rFrom->verse_number : 1;
                                                                $to   = $s->id === $rTo->surah_id   ? $rTo->verse_number   : $s->verses_count;
                                                                $rLinks[] = [
                                                                    'name' => $s->name_arabic,
                                                                    'url'  => 'https://quran.com/ar/' . $s->number . '/' . $from . '-' . $to,
                                                                ];
                                                            }
                                                        }
                                                    } elseif ($rFrom) {
                                                        $rLinks[] = [
                                                            'name' => $rFrom->surah->name_arabic,
                                                            'url'  => 'https://quran.com/ar/' . $rFrom->surah->number . '/' . $rFrom->verse_number . '-' . $rFrom->surah->verses_count,
                                                        ];
                                                    }
                                                @endphp
                                                @if(count($rLinks) === 1)
                                                    <a href="{{ $rLinks[0]['url'] }}" target="_blank"
                                                        class="inline-flex items-center gap-1.5 mt-3 px-2.5 py-1 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-500/30 transition-colors">
                                                        <flux:icon icon="book-open" class="size-3.5" />
                                                        {{ __('افتح') }} {{ $rLinks[0]['name'] }}
                                                    </a>
                                                @elseif(count($rLinks) > 1)
                                                    <div x-data="{ open: false }" class="mt-3">
                                                        <button type="button" @click="open = !open"
                                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-500/30 transition-colors">
                                                            <flux:icon icon="book-open" class="size-3.5" />
                                                            <span>{{ __('افتح الآيات في القرآن') }} ({{ count($rLinks) }})</span>
                                                            <flux:icon icon="chevron-down" class="size-3.5 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                                                        </button>
                                                        <div x-show="open" x-collapse class="flex flex-wrap gap-2 mt-2">
                                                            @foreach($rLinks as $link)
                                                                <a href="{{ $link['url'] }}" target="_blank"
                                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-500/30 transition-colors">
                                                                    <flux:icon icon="book-open" class="size-3.5" />
                                                                    {{ $link['name'] }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            <flux:separator />

                                            <div>
                                                <flux:label class="mb-3 font-semibold">{{ __('تقييم الإنجاز (التسميع)') }}</flux:label>
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                    <button type="button" @click="syncingTask = 'review-3'; await $wire.saveAchievement({{ $currentDay->id }}, 'review', 3); syncingTask = null"
                                                        :disabled="syncingTask !== null"
                                                        class="p-3 rounded-xl border-2 transition-colors font-bold text-center disabled:opacity-50 disabled:cursor-wait"
                                                        :class="syncingTask === 'review-3' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->review_achievement === 3 ? 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-700 dark:text-green-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-green-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">ممتاز</button>

                                                    <button type="button" @click="syncingTask = 'review-2'; await $wire.saveAchievement({{ $currentDay->id }}, 'review', 2); syncingTask = null"
                                                        :disabled="syncingTask !== null"
                                                        class="p-3 rounded-xl border-2 transition-colors font-bold text-center disabled:opacity-50 disabled:cursor-wait"
                                                        :class="syncingTask === 'review-2' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->review_achievement === 2 ? 'border-blue-500 bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-blue-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">جيد</button>

                                                    <button type="button" @click="syncingTask = 'review-1'; await $wire.saveAchievement({{ $currentDay->id }}, 'review', 1); syncingTask = null"
                                                        :disabled="syncingTask !== null"
                                                        class="p-3 rounded-xl border-2 transition-colors font-bold text-center disabled:opacity-50 disabled:cursor-wait"
                                                        :class="syncingTask === 'review-1' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->review_achievement === 1 ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-amber-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">مقبول</button>

                                                    <button type="button" @click="syncingTask = 'review-null'; await $wire.saveAchievement({{ $currentDay->id }}, 'review', null); syncingTask = null"
                                                        :disabled="syncingTask !== null"
                                                        class="p-3 rounded-xl border-2 transition-colors font-bold text-center disabled:opacity-50 disabled:cursor-wait"
                                                        :class="syncingTask === 'review-null' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->review_achievement === null ? 'border-red-500 bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-red-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">لم يسمع</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </flux:card>
                        @endif
                    @else
                        <div class="flex flex-col items-center justify-center p-12 bg-zinc-50/50 dark:bg-zinc-900/50 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl text-center h-full min-h-[400px]">
                            <flux:icon icon="document-text" class="size-16 text-zinc-300 dark:text-zinc-600 mb-4" />
                            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400 mb-2">{{ __('لا توجد خطط لهذا الطالب') }}</flux:heading>
                            <p class="text-zinc-400 dark:text-zinc-500 text-sm max-w-sm mb-6">{{ __('قم بإنشاء خطة قرآنية للطالب للبدء بتقييم التسميع والمراجعة.') }}</p>
                            <flux:button href="{{ route('teacher.plan-creator', ['studentId' => $student->id]) }}" variant="primary" icon="plus">{{ __('إنشاء خطة جديدة') }}</flux:button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Session Settings Modal -->
    <flux:modal wire:model="showSessionModal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('إعدادات طابور التسميع') }}</flux:heading>
                <flux:subheading>{{ __('قم بتحديد جدول طابور التسميع والأيام التي سيكون متاحاً فيها للطلاب.') }}
                </flux:subheading>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <livewire:shared.hijri-datepicker wire:model="sessionStartDate" label="{{ __('تاريخ البداية') }}" />
                    <livewire:shared.hijri-datepicker wire:model="sessionEndDate" label="{{ __('تاريخ النهاية') }}" />
                </div>

                <flux:field>
                    <flux:label>{{ __('أيام الحجز') }}</flux:label>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mt-2">
                        <flux:checkbox wire:model="sessionDaysOfWeek" value="0" label="الأحد" />
                        <flux:checkbox wire:model="sessionDaysOfWeek" value="1" label="الإثنين" />
                        <flux:checkbox wire:model="sessionDaysOfWeek" value="2" label="الثلاثاء" />
                        <flux:checkbox wire:model="sessionDaysOfWeek" value="3" label="الأربعاء" />
                        <flux:checkbox wire:model="sessionDaysOfWeek" value="4" label="الخميس" />
                        <flux:checkbox wire:model="sessionDaysOfWeek" value="5" label="الجمعة" />
                        <flux:checkbox wire:model="sessionDaysOfWeek" value="6" label="السبت" />
                    </div>
                    <flux:error name="sessionDaysOfWeek" />
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="time" wire:model="sessionStartTime" label="{{ __('وقت بداية الحجز') }}" />
                    <flux:input type="time" wire:model="sessionEndTime" label="{{ __('وقت نهاية الحجز') }}" />
                </div>

                <div class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg flex gap-2">
                    <flux:icon icon="information-circle" class="size-4 shrink-0 mt-0.5" />
                    <p>{{ __('الأدوار تتجدد يومياً بناءً على هذا الجدول. في الوقت المحدد سيظهر للطلاب زر لطلب رقم. الطلاب أصحاب الأرقام سيظهرون أعلى قائمة "حاضر" هنا.') }}
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showSessionModal', false)" variant="ghost">{{ __('إلغاء') }}
                </flux:button>
                <flux:button wire:click="saveSession" variant="primary"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white border-none">{{ __('حفظ التفعيل') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>