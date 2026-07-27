<?php

use Livewire\Component;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\StudentOdePlan;
use App\Models\StudentOdeAchievement;
use App\Models\OdePath;
use App\Models\OdePathDay;
use App\Models\StudentHadithPlan;
use App\Models\StudentHadithAchievement;
use App\Models\HadithPath;
use App\Models\HadithPathDay;
use App\Models\GamificationTrack;
use App\Services\GamificationService;
use Flux\Flux;
use Livewire\Attributes\Reactive;

new class extends Component {
    public $student;

    public $sPlans;

    public $activePlanId;

    #[Reactive]
    public $gradedAtDate;

    public $selectedPlanId;

    public function mount($student, $sPlans, $activePlanId)
    {
        $this->student = $student;
        $this->sPlans = $sPlans;
        $this->activePlanId = $activePlanId;
        $this->selectedPlanId = $this->activePlanId;
    }

    public function selectPlan($planId)
    {
        $this->selectedPlanId = $planId;
    }

    public function saveAchievement($dayId, $type, $value)
    {
        $updateData = [];
        $gradeTime = now();
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

        $day = StudentPlanDay::find($dayId);
        if ($day) {
            \App\Services\GamificationService::syncStudentPlanDayXP($day);

            if ($value !== null) {
                $label = $type === 'hifz' ? 'الحفظ' : 'المراجعة';
                \App\Services\NotificationService::notify(
                    'student',
                    $this->student->id,
                    'grading',
                    'تقييم جديد',
                    "قام معلمك بتقييم {$label} الخاص بك",
                    route('student.hifz'),
                );
            }
        }

        Flux::toast('تم حفظ التقييم', variant: 'success');
    }

    public function saveOdeAchievement($pathDayId, $type, $value)
    {
        // Find the student's active ode plan
        $activeOdePlan = StudentOdePlan::where('student_id', $this->student->id)
            ->where('status', 'active')
            ->first();

        if (!$activeOdePlan) {
            Flux::toast('لا توجد خطة منظومة نشطة لهذا الطالب', variant: 'danger');
            return;
        }

        $updateData = [];
        $gradeTime = now();
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

        $achievement = StudentOdeAchievement::updateOrCreate(
            [
                'student_ode_plan_id' => $activeOdePlan->id,
                'ode_path_day_id' => $pathDayId,
            ],
            $updateData
        );

        \App\Services\GamificationService::syncStudentOdeAchievementXP($achievement->fresh(['plan.student', 'pathDay']));

        if ($value !== null) {
            $label = $type === 'hifz' ? 'الحفظ' : 'المراجعة';
            \App\Services\NotificationService::notify(
                'student',
                $this->student->id,
                'grading',
                'تقييم جديد',
                "قام معلمك بتقييم {$label} الخاص بك في المنظومة",
                route('student.hifz'),
            );
        }

        Flux::toast('تم حفظ تقييم المنظومة بنجاح', variant: 'success');
    }

    public function saveHadithAchievement($pathDayId, $type, $value)
    {
        // Find the student's active hadith plan
        $activeHadithPlan = StudentHadithPlan::where('student_id', $this->student->id)
            ->where('status', 'active')
            ->first();

        if (!$activeHadithPlan) {
            Flux::toast('لا توجد خطة حديث نشطة لهذا الطالب', variant: 'danger');
            return;
        }

        $updateData = [];
        $gradeTime = now();
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

        $achievement = StudentHadithAchievement::updateOrCreate(
            [
                'student_hadith_plan_id' => $activeHadithPlan->id,
                'hadith_path_day_id' => $pathDayId,
            ],
            $updateData
        );

        \App\Services\GamificationService::syncStudentHadithAchievementXP($achievement->fresh(['plan.student', 'pathDay']));

        if ($value !== null) {
            $label = $type === 'hifz' ? 'الحفظ' : 'المراجعة';
            \App\Services\NotificationService::notify(
                'student',
                $this->student->id,
                'grading',
                'تقييم جديد',
                "قام معلمك بتقييم {$label} الخاص بك في الحديث",
                route('student.hifz'),
            );
        }

        Flux::toast('تم حفظ تقييم الحديث بنجاح', variant: 'success');
    }

    // --- Path & track enrollment (teacher, permission-gated) -------------------

    /** @var 'hadith'|'ode'|'' */
    public string $pathModalType = '';

    public bool $showPathModal = false;

    /** @var array<int, array{id:int, name:string, text:?string}> */
    public array $availablePaths = [];

    public ?int $selectedPathId = null;

    public bool $showTrackModal = false;

    /** @var array<int, array{id:int, title:string, tracks:array<int, array{id:int, name:string}>}> */
    public array $availableCompetitions = [];

    /** @var array<int, ?int> map of leaderboard_id => selected track_id (or null) */
    public array $trackSelections = [];

    /**
     * The effective permissions of the acting teacher (empty when not a teacher).
     *
     * @return array<string, bool>
     */
    private function teacherPermissions(): array
    {
        return auth('teacher')->user()?->effectivePermissions() ?? [];
    }

    public function openPathModal(string $type): void
    {
        $permKey = $type === 'ode' ? 'can_manage_ode_paths' : 'can_manage_hadith_paths';
        if (empty($this->teacherPermissions()[$permKey])) {
            Flux::toast('لا تملك صلاحية إدارة هذا النوع من المسارات', variant: 'danger');
            return;
        }

        $this->pathModalType = $type;

        if ($type === 'ode') {
            $this->availablePaths = OdePath::with('ode')->orderBy('name')->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'text' => $p->ode?->name])
                ->all();
            $this->selectedPathId = StudentOdePlan::where('student_id', $this->student->id)
                ->where('status', 'active')->value('ode_path_id');
        } else {
            $this->availablePaths = HadithPath::with('text')->orderBy('name')->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'text' => $p->text?->name])
                ->all();
            $this->selectedPathId = StudentHadithPlan::where('student_id', $this->student->id)
                ->where('status', 'active')->value('hadith_path_id');
        }

        $this->showPathModal = true;
    }

    public function enrollInPath(): void
    {
        $type = $this->pathModalType;
        $permKey = $type === 'ode' ? 'can_manage_ode_paths' : 'can_manage_hadith_paths';
        if (empty($this->teacherPermissions()[$permKey])) {
            Flux::toast('لا تملك صلاحية إدارة هذا النوع من المسارات', variant: 'danger');
            return;
        }

        if ($type === 'ode') {
            $current = StudentOdePlan::where('student_id', $this->student->id)->where('status', 'active')->first();
            if ($current && $current->ode_path_id === (int) $this->selectedPathId) {
                $this->showPathModal = false;
                return;
            }
            // Suspend any current active ode plan, then enroll in the chosen path.
            StudentOdePlan::where('student_id', $this->student->id)->where('status', 'active')
                ->update(['status' => 'suspended']);
            if ($this->selectedPathId) {
                $path = OdePath::findOrFail($this->selectedPathId);
                StudentOdePlan::create([
                    'student_id' => $this->student->id,
                    'ode_path_id' => $path->id,
                    'start_date' => $path->start_date,
                    'status' => 'active',
                    'created_by_role' => 'teacher',
                ]);
                Flux::toast('تم تسكين الطالب في مسار المنظومة', variant: 'success');
            } else {
                Flux::toast('تم إلغاء تسكين الطالب من مسار المنظومة', variant: 'success');
            }
        } else {
            $current = StudentHadithPlan::where('student_id', $this->student->id)->where('status', 'active')->first();
            if ($current && $current->hadith_path_id === (int) $this->selectedPathId) {
                $this->showPathModal = false;
                return;
            }
            StudentHadithPlan::where('student_id', $this->student->id)->where('status', 'active')
                ->update(['status' => 'suspended']);
            if ($this->selectedPathId) {
                $path = HadithPath::findOrFail($this->selectedPathId);
                StudentHadithPlan::create([
                    'student_id' => $this->student->id,
                    'hadith_path_id' => $path->id,
                    'start_date' => $path->start_date,
                    'status' => 'active',
                    'created_by_role' => 'teacher',
                ]);
                Flux::toast('تم تسكين الطالب في مسار الحديث', variant: 'success');
            } else {
                Flux::toast('تم إلغاء تسكين الطالب من مسار الحديث', variant: 'success');
            }
        }

        $this->showPathModal = false;
    }

    public function openTrackModal(): void
    {
        if (empty($this->teacherPermissions()['can_manage_gamification_tracks'])) {
            Flux::toast('لا تملك صلاحية تسكين الطلاب في مسارات التلعيب', variant: 'danger');
            return;
        }

        $leaderboards = GamificationService::getActiveLeaderboards($this->student);

        $currentByLeaderboard = GamificationTrack::query()
            ->whereIn('leaderboard_id', $leaderboards->pluck('id'))
            ->whereHas('students', fn ($q) => $q->where('users.id', $this->student->id))
            ->get(['id', 'leaderboard_id'])
            ->keyBy('leaderboard_id');

        $this->availableCompetitions = [];
        $this->trackSelections = [];

        foreach ($leaderboards as $leaderboard) {
            $tracks = GamificationTrack::where('leaderboard_id', $leaderboard->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'name']);

            if ($tracks->isEmpty()) {
                continue;
            }

            $this->availableCompetitions[] = [
                'id' => $leaderboard->id,
                'title' => $leaderboard->title,
                'tracks' => $tracks->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all(),
            ];
            $this->trackSelections[$leaderboard->id] = $currentByLeaderboard->get($leaderboard->id)?->id;
        }

        $this->showTrackModal = true;
    }

    public function saveTrackEnrollment(): void
    {
        if (empty($this->teacherPermissions()['can_manage_gamification_tracks'])) {
            Flux::toast('لا تملك صلاحية تسكين الطلاب في مسارات التلعيب', variant: 'danger');
            return;
        }

        foreach ($this->availableCompetitions as $competition) {
            $trackIds = collect($competition['tracks'])->pluck('id')->all();
            $selectedTrackId = $this->trackSelections[$competition['id']] ?? null;

            // Enforce one track per competition: detach from all this competition's
            // tracks, then attach the chosen one (if any).
            \Illuminate\Support\Facades\DB::table('gamification_track_student')
                ->where('student_id', $this->student->id)
                ->whereIn('track_id', $trackIds)
                ->delete();

            if ($selectedTrackId && in_array((int) $selectedTrackId, $trackIds, true)) {
                GamificationTrack::find($selectedTrackId)?->students()->syncWithoutDetaching([$this->student->id]);
            }
        }

        $this->showTrackModal = false;
        Flux::toast('تم تحديث تسكين الطالب في مسارات التلعيب', variant: 'success');
    }

    public function with()
    {
        // Fetch student's Poetic Ode plans
        $studentOdePlans = collect();
        $activeOdePlan = null;
        if (app()->bound('tasmeeh_ode_plans_cache')) {
            $activeOdePlan = app('tasmeeh_ode_plans_cache')->get($this->student->id);
            if ($activeOdePlan) {
                $studentOdePlans->push($activeOdePlan);
            }
        } else {
            $studentOdePlans = StudentOdePlan::with('path.ode')
                ->where('student_id', $this->student->id)
                ->get();
            $activeOdePlan = $studentOdePlans->firstWhere('status', 'active');
        }

        // Fetch student's Hadith plans
        $studentHadithPlans = collect();
        $activeHadithPlan = null;
        if (app()->bound('tasmeeh_hadith_plans_cache')) {
            $activeHadithPlan = app('tasmeeh_hadith_plans_cache')->get($this->student->id);
            if ($activeHadithPlan) {
                $studentHadithPlans->push($activeHadithPlan);
            }
        } else {
            $studentHadithPlans = StudentHadithPlan::with('path')
                ->where('student_id', $this->student->id)
                ->get();
            $activeHadithPlan = $studentHadithPlans->firstWhere('status', 'active');
        }

        // Selected Quranic plan (inactive plans never show on the tasmeeh page)
        $activePlan = null;
        if ($this->selectedPlanId) {
            $activePlan = $this->sPlans->where('status', 'active')->firstWhere('id', $this->selectedPlanId);
        }
        if (!$activePlan) {
            $activePlan = $this->sPlans->firstWhere('status', 'active');
        }

        $days = collect();
        $defaultDayId = null;
        $dayIds = [];

        // Fetch Poetic Ode days for the active Ode plan
        $odeDays = collect();
        if ($activeOdePlan) {
            if (app()->bound('tasmeeh_ode_days_cache')) {
                $odeDays = app('tasmeeh_ode_days_cache')->get($activeOdePlan->id) ?? collect();
            } else {
                $rawOdeDays = OdePathDay::where('ode_path_id', $activeOdePlan->ode_path_id)
                    ->orderBy('day_number', 'asc')
                    ->get();

                $odeAchievements = StudentOdeAchievement::where('student_ode_plan_id', $activeOdePlan->id)
                    ->get()
                    ->keyBy('ode_path_day_id');

                $odeDays = $rawOdeDays->map(function ($day) use ($odeAchievements) {
                    $achievement = $odeAchievements->get($day->id);
                    $day->hifz_achievement = $achievement?->hifz_achievement;
                    $day->review_achievement = $achievement?->review_achievement;
                    $day->hifz_graded_at = $achievement?->hifz_graded_at;
                    $day->review_graded_at = $achievement?->review_graded_at;
                    return $day;
                });
            }
        }

        // Fetch Hadith days for the active Hadith plan
        $hadithDays = collect();
        if ($activeHadithPlan) {
            if (app()->bound('tasmeeh_hadith_days_cache')) {
                $hadithDays = app('tasmeeh_hadith_days_cache')->get($activeHadithPlan->id) ?? collect();
            } else {
                $rawDays = HadithPathDay::with(['fromHadith', 'toHadith', 'reviewFromHadith', 'reviewToHadith'])
                    ->where('hadith_path_id', $activeHadithPlan->hadith_path_id)
                    ->orderBy('day_number', 'asc')
                    ->get();

                $achievements = StudentHadithAchievement::where('student_hadith_plan_id', $activeHadithPlan->id)
                    ->get()
                    ->keyBy('hadith_path_day_id');

                $hadithDays = $rawDays->map(function ($day) use ($achievements) {
                    $achievement = $achievements->get($day->id);
                    $day->hifz_achievement = $achievement?->hifz_achievement;
                    $day->review_achievement = $achievement?->review_achievement;
                    $day->hifz_graded_at = $achievement?->hifz_graded_at;
                    $day->review_graded_at = $achievement?->review_graded_at;
                    return $day;
                });
            }
        }


        // Find the specific Ode plan day matching the selected gradedAtDate
        $odeDayForSelectedDate = null;
        if ($activeOdePlan && $odeDays->isNotEmpty()) {
            $targetDateStr = $this->gradedAtDate ?: now()->format('Y-m-d');
            $odeDayForSelectedDate = $odeDays->first(function ($day) use ($targetDateStr) {
                return $day->date->toDateString() === $targetDateStr;
            });
        }

        // Find the specific Hadith plan day matching the selected gradedAtDate
        $hadithDayForSelectedDate = null;
        if ($activeHadithPlan && $hadithDays->isNotEmpty()) {
            $targetDateStr = $this->gradedAtDate ?: now()->format('Y-m-d');
            $hadithDayForSelectedDate = $hadithDays->first(function ($day) use ($targetDateStr) {
                return $day->date->toDateString() === $targetDateStr;
            });
        }

        // Load Quranic plan days
        if ($activePlan) {
            if (app()->bound('tasmeeh_days_cache')) {
                $days = app('tasmeeh_days_cache')->get($activePlan->id) ?? collect();
            } else {
                $days = StudentPlanDay::with(['fromAyah.surah', 'toAyah.surah', 'reviewFromAyah.surah', 'reviewToAyah.surah', 'plan'])
                    ->where('student_plan_id', $activePlan->id)
                    ->orderBy('date', 'asc')
                    ->get();
            }

            if ($days->isNotEmpty()) {
                $dayIds = $days->pluck('id')->toArray();

                $oldestIncomplete = $days->first(function ($day) use ($activePlan) {
                    if ($activePlan->plan_type === 'hifz') {
                        return is_null($day->hifz_achievement);
                    } elseif ($activePlan->plan_type === 'review') {
                        return is_null($day->review_achievement);
                    } else {
                        return is_null($day->hifz_achievement) || is_null($day->review_achievement);
                    }
                });

                if ($oldestIncomplete) {
                    $defaultDayId = $oldestIncomplete->id;
                } else {
                    $defaultDayId = $days->last()->id;
                }
            }
        }

        // Request-static cache to completely avoid N+1 queries for Surah loading inside loops
        static $cachedSurahs = null;
        if ($cachedSurahs === null) {
            $cachedSurahs = \App\Models\Surah::all()->keyBy('id');
        }

        $defaultOdeDayId = null;
        $odeDayIds = [];
        $odeDateToDayIdMap = [];
        if ($activeOdePlan && $odeDays->isNotEmpty()) {
            $odeDayIds = $odeDays->pluck('id')->toArray();
            $odeDateToDayIdMap = $odeDays->mapWithKeys(function ($day) {
                return [$day->date->toDateString() => $day->id];
            })->toArray();

            if ($odeDayForSelectedDate) {
                $defaultOdeDayId = $odeDayForSelectedDate->id;
            } else {
                $oldestIncompleteOde = $odeDays->first(function ($day) {
                    $hasHifz = $day->from_verse_number && $day->to_verse_number;
                    $hasReview = $day->review_from_verse_number && $day->review_to_verse_number;

                    if ($hasHifz && $hasReview) {
                        return is_null($day->hifz_achievement) || is_null($day->review_achievement);
                    } elseif ($hasHifz) {
                        return is_null($day->hifz_achievement);
                    } elseif ($hasReview) {
                        return is_null($day->review_achievement);
                    }
                    return false;
                });

                if ($oldestIncompleteOde) {
                    $defaultOdeDayId = $oldestIncompleteOde->id;
                } else {
                    $defaultOdeDayId = $odeDays->last()->id;
                }
            }
        }

        $defaultHadithDayId = null;
        $hadithDayIds = [];
        $hadithDateToDayIdMap = [];
        if ($activeHadithPlan && $hadithDays->isNotEmpty()) {
            $hadithDayIds = $hadithDays->pluck('id')->toArray();
            $hadithDateToDayIdMap = $hadithDays->mapWithKeys(function ($day) {
                return [$day->date->toDateString() => $day->id];
            })->toArray();

            if ($hadithDayForSelectedDate) {
                $defaultHadithDayId = $hadithDayForSelectedDate->id;
            } else {
                $oldestIncompleteHadith = $hadithDays->first(function ($day) {
                    $hasHifz = $day->from_line_number && $day->to_line_number;
                    $hasReview = $day->review_from_line_number && $day->review_to_line_number;

                    if ($hasHifz && $hasReview) {
                        return is_null($day->hifz_achievement) || is_null($day->review_achievement);
                    } elseif ($hasHifz) {
                        return is_null($day->hifz_achievement);
                    } elseif ($hasReview) {
                        return is_null($day->review_achievement);
                    }
                    return false;
                });

                if ($oldestIncompleteHadith) {
                    $defaultHadithDayId = $oldestIncompleteHadith->id;
                } else {
                    $defaultHadithDayId = $hadithDays->last()->id;
                }
            }
        }

        $quranDateToDayIdMap = [];
        if ($activePlan && $days->isNotEmpty()) {
            $quranDateToDayIdMap = $days->mapWithKeys(function ($day) {
                return [$day->date->toDateString() => $day->id];
            })->toArray();
        }

        // Fetch all verses of the active Ode plan's ode
        $odeVerses = collect();
        if ($activeOdePlan) {
            $odeVerses = \App\Models\OdeVerse::where('ode_id', $activeOdePlan->path->ode_id)
                ->orderBy('verse_number', 'asc')
                ->get();
        }

        // Fetch all hadiths and lines of the active Hadith plan's text
        $allHadiths = collect();
        if ($activeHadithPlan) {
            $allHadiths = \App\Models\Hadith::with('lines')->where(function ($query) use ($activeHadithPlan) {
                $query->where('hadith_text_id', $activeHadithPlan->path->hadith_text_id)
                    ->orWhereHas('chapter', function ($q) use ($activeHadithPlan) {
                        $q->where('hadith_text_id', $activeHadithPlan->path->hadith_text_id);
                    });
            })->orderBy('hadith_chapter_id', 'asc')->orderBy('id', 'asc')->get();
        }

        // Fetch all lines of the active Hadith plan's selected range
        $hadithLines = collect();
        if ($activeHadithPlan && $hadithDayForSelectedDate && $allHadiths->isNotEmpty()) {
            if ($hadithDayForSelectedDate->memorize_type === 'lines') {
                $hadith = $allHadiths->first(fn($h) => $h->id == $hadithDayForSelectedDate->from_hadith_id);
                if ($hadith) {
                    $hadithLines = $hadith->lines;
                }
            } else {
                $startIdx = $allHadiths->search(fn($h) => $h->id == $hadithDayForSelectedDate->from_hadith_id);
                $endIdx = $allHadiths->search(fn($h) => $h->id == $hadithDayForSelectedDate->to_hadith_id);
                if ($startIdx !== false && $endIdx !== false) {
                    $selectedHadiths = $allHadiths->slice($startIdx, $endIdx - $startIdx + 1);
                    foreach ($selectedHadiths as $hadith) {
                        foreach ($hadith->lines as $line) {
                            $hadithLines->push($line);
                        }
                    }
                }
            }
        }

        $perms = $this->teacherPermissions();

        // Lightweight summary of the tracks this student currently belongs to,
        // only when the teacher may manage tracks (keeps the default render lean).
        $currentTracks = collect();
        if (! empty($perms['can_manage_gamification_tracks'])) {
            $currentTracks = GamificationTrack::query()
                ->whereHas('students', fn ($q) => $q->where('users.id', $this->student->id))
                ->with('leaderboard:id,title')
                ->get(['id', 'name', 'leaderboard_id']);
        }

        return [
            'student' => $this->student,
            'sPlans' => $this->sPlans,
            'activePlan' => $activePlan,
            'activeOdePlan' => $activeOdePlan,
            'odeDayForSelectedDate' => $odeDayForSelectedDate,
            'activeHadithPlan' => $activeHadithPlan,
            'hadithDayForSelectedDate' => $hadithDayForSelectedDate,
            'days' => $days,
            'allSurahs' => $cachedSurahs,
            'defaultDayId' => $defaultDayId,
            'dayIds' => $dayIds,
            'odeDays' => $odeDays,
            'defaultOdeDayId' => $defaultOdeDayId,
            'odeDayIds' => $odeDayIds,
            'odeDateToDayIdMap' => $odeDateToDayIdMap,
            'quranDateToDayIdMap' => $quranDateToDayIdMap,
            'odeVerses' => $odeVerses,
            'hadithDays' => $hadithDays,
            'defaultHadithDayId' => $defaultHadithDayId,
            'hadithDayIds' => $hadithDayIds,
            'hadithDateToDayIdMap' => $hadithDateToDayIdMap,
            'hadithLines' => $hadithLines,
            'allHadiths' => $allHadiths,
            'perms' => $perms,
            'currentTracks' => $currentTracks,
        ];
    }

    public function placeholder(): string
    {
        return '<div class="animate-pulse h-32 rounded-xl bg-zinc-100 dark:bg-zinc-800 mb-2"></div>';
    }
};
?>

<div x-data="{
    activeDayId: @js($defaultDayId),
    studentPlanDayIds: @js($dayIds),
    activeOdeDayId: @js($defaultOdeDayId),
    studentOdePlanDayIds: @js($odeDayIds),
    odeDateToDayIdMap: @js($odeDateToDayIdMap),
    activeHadithDayId: @js($defaultHadithDayId),
    studentHadithPlanDayIds: @js($hadithDayIds),
    hadithDateToDayIdMap: @js($hadithDateToDayIdMap),
    quranDateToDayIdMap: @js($quranDateToDayIdMap),

    init() {
        this.$watch('$wire.gradedAtDate', (newVal) => {
            if (this.odeDateToDayIdMap && this.odeDateToDayIdMap[newVal]) {
                this.activeOdeDayId = this.odeDateToDayIdMap[newVal];
            }
            if (this.hadithDateToDayIdMap && this.hadithDateToDayIdMap[newVal]) {
                this.activeHadithDayId = this.hadithDateToDayIdMap[newVal];
            }
            if (this.quranDateToDayIdMap && this.quranDateToDayIdMap[newVal]) {
                this.activeDayId = this.quranDateToDayIdMap[newVal];
            }
        });
    },
    hasPrevDay() {
        const index = this.studentPlanDayIds.findIndex(id => id == this.activeDayId);
        return index > 0;
    },
    hasNextDay() {
        const index = this.studentPlanDayIds.findIndex(id => id == this.activeDayId);
        return index !== -1 && index < this.studentPlanDayIds.length - 1;
    },
    prevDay() {
        const index = this.studentPlanDayIds.findIndex(id => id == this.activeDayId);
        if (index > 0) {
            this.activeDayId = this.studentPlanDayIds[index - 1];
        }
    },
    nextDay() {
        const index = this.studentPlanDayIds.findIndex(id => id == this.activeDayId);
        if (index !== -1 && index < this.studentPlanDayIds.length - 1) {
            this.activeDayId = this.studentPlanDayIds[index + 1];
        }
    },
    hasPrevOdeDay() {
        const index = this.studentOdePlanDayIds.findIndex(id => id == this.activeOdeDayId);
        return index > 0;
    },
    hasNextOdeDay() {
        const index = this.studentOdePlanDayIds.findIndex(id => id == this.activeOdeDayId);
        return index !== -1 && index < this.studentOdePlanDayIds.length - 1;
    },
    prevOdeDay() {
        const index = this.studentOdePlanDayIds.findIndex(id => id == this.activeOdeDayId);
        if (index > 0) {
            this.activeOdeDayId = this.studentOdePlanDayIds[index - 1];
        }
    },
    nextOdeDay() {
        const index = this.studentOdePlanDayIds.findIndex(id => id == this.activeOdeDayId);
        if (index !== -1 && index < this.studentOdePlanDayIds.length - 1) {
            this.activeOdeDayId = this.studentOdePlanDayIds[index + 1];
        }
    },
    hasPrevHadithDay() {
        const index = this.studentHadithPlanDayIds.findIndex(id => id == this.activeHadithDayId);
        return index > 0;
    },
    hasNextHadithDay() {
        const index = this.studentHadithPlanDayIds.findIndex(id => id == this.activeHadithDayId);
        return index !== -1 && index < this.studentHadithPlanDayIds.length - 1;
    },
    prevHadithDay() {
        const index = this.studentHadithPlanDayIds.findIndex(id => id == this.activeHadithDayId);
        if (index > 0) {
            this.activeHadithDayId = this.studentHadithPlanDayIds[index - 1];
        }
    },
    nextHadithDay() {
        const index = this.studentHadithPlanDayIds.findIndex(id => id == this.activeHadithDayId);
        if (index !== -1 && index < this.studentHadithPlanDayIds.length - 1) {
            this.activeHadithDayId = this.studentHadithPlanDayIds[index + 1];
        }
    }
}"
    class="space-y-4"
    wire:key="student-tasmeeh-container-{{ $student->id }}-{{ $selectedPlanId ?? 'ode-only' }}"
>
    @if($sPlans->isNotEmpty())
        <div class="bg-white dark:bg-zinc-900 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm mb-4">
            <flux:select wire:change="selectPlan($event.target.value)" label="{{ __('الخطة القرآنية') }}" placeholder="{{ __('اختر الخطة') }}">
                @foreach($sPlans as $plan)
                    <flux:select.option value="{{ $plan->id }}" :selected="$selectedPlanId == $plan->id">
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
    @endif

    @if($sPlans->isNotEmpty())
        @foreach($days as $day)
            @php $currentDay = $day; @endphp
            <flux:card wire:key="day-card-{{ $day->id }}" x-show="activeDayId == {{ $day->id }}" x-data="{ syncingTask: null }" class="mt-2 border-zinc-200 dark:border-zinc-700" wire:loading.class="opacity-50 pointer-events-none transition-opacity duration-200" wire:target="saveAchievement">

                {{-- Day navigation --}}
                <div class="flex items-center justify-between mb-4 md:mb-8 border-b border-zinc-100 dark:border-zinc-800 pb-3 md:pb-4">
                    <flux:button type="button" @click="prevDay()" x-bind:disabled="!hasPrevDay()" icon="chevron-right" variant="subtle" size="sm">
                        {{ __('اليوم السابق') }}
                    </flux:button>

                    <div class="text-center">
                        <div class="font-bold text-base md:text-lg">{{ $day->day_name }}</div>
                        <div class="text-zinc-500 text-xs md:text-sm dir-ltr">{{ $day->date->format('Y/m/d') }}</div>
                    </div>

                    <flux:button type="button" @click="nextDay()" x-bind:disabled="!hasNextDay()" icon-trailing="chevron-left" variant="subtle" size="sm">
                        {{ __('اليوم التالي') }}
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-8">
                    {{-- Hifz Section --}}
                    @if($currentDay->plan->plan_type === 'hifz' || $currentDay->plan->plan_type === 'hifz_review')
                        <div class="bg-indigo-50/50 dark:bg-indigo-500/5 rounded-xl border border-indigo-100 dark:border-indigo-500/20 p-3.5 md:p-5 space-y-3 md:space-y-5">
                            <div>
                                <flux:heading size="lg" class="text-indigo-600 dark:text-indigo-400 mb-1 md:mb-2">{{ __('الحفظ') }}</flux:heading>
                                <p class="text-zinc-700 dark:text-zinc-300 font-medium text-base md:text-lg leading-snug md:leading-relaxed">
                                    {{ $currentDay->formatRange('hifz') ?? 'لا يوجد نص محدد' }}
                                </p>
                                @php
                                    $hLinks = [];
                                    $hFrom = $currentDay->fromAyah;
                                    $hTo = $currentDay->toAyah;
                                    if ($hFrom && $hTo) {
                                        if ($hFrom->surah_id === $hTo->surah_id) {
                                            $hLinks[] = [
                                                'name' => $hFrom->surah->name_arabic,
                                                'url' => 'https://quran.com/ar/' . $hFrom->surah->number . '/' . $hFrom->verse_number . '-' . $hTo->verse_number,
                                            ];
                                        } else {
                                            $low = min($hFrom->surah_id, $hTo->surah_id);
                                            $high = max($hFrom->surah_id, $hTo->surah_id);
                                            $direction = $hFrom->surah_id <= $hTo->surah_id ? 'asc' : 'desc';
                                            $surahs = $allSurahs->filter(fn($s) => $s->id >= $low && $s->id <= $high);
                                            if ($direction === 'desc') {
                                                $surahs = $surahs->sortByDesc('id');
                                            } else {
                                                $surahs = $surahs->sortBy('id');
                                            }
                                            foreach ($surahs as $s) {
                                                $from = $s->id === $hFrom->surah_id ? $hFrom->verse_number : 1;
                                                $to = $s->id === $hTo->surah_id ? $hTo->verse_number : $s->verses_count;
                                                $hLinks[] = [
                                                    'name' => $s->name_arabic,
                                                    'url' => 'https://quran.com/ar/' . $s->number . '/' . $from . '-' . $to,
                                                ];
                                            }
                                        }
                                    } elseif ($hFrom) {
                                        $hLinks[] = [
                                            'name' => $hFrom->surah->name_arabic,
                                            'url' => 'https://quran.com/ar/' . $hFrom->surah->number . '/' . $hFrom->verse_number . '-' . $hFrom->surah->verses_count,
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
                                <flux:label class="mb-2 md:mb-3 text-xs md:text-sm font-semibold">{{ __('تقييم الإنجاز (التسميع)') }}</flux:label>
                                {{-- Four across even on a phone: two rows of grade buttons pushed the review card off-screen. --}}
                                <div class="grid grid-cols-4 gap-1.5 md:gap-2">
                                    <button type="button" @click="syncingTask = 'hifz-3'; await $wire.saveAchievement({{ $currentDay->id }}, 'hifz', 3); syncingTask = null"
                                        :disabled="syncingTask !== null"
                                        class="px-1 py-2.5 md:p-3 rounded-lg md:rounded-xl border-2 transition-colors font-bold text-center text-xs md:text-base disabled:opacity-50 disabled:cursor-wait"
                                        :class="syncingTask === 'hifz-3' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->hifz_achievement === 3 ? 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-700 dark:text-green-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-green-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">ممتاز</button>

                                    <button type="button" @click="syncingTask = 'hifz-2'; await $wire.saveAchievement({{ $currentDay->id }}, 'hifz', 2); syncingTask = null"
                                        :disabled="syncingTask !== null"
                                        class="px-1 py-2.5 md:p-3 rounded-lg md:rounded-xl border-2 transition-colors font-bold text-center text-xs md:text-base disabled:opacity-50 disabled:cursor-wait"
                                        :class="syncingTask === 'hifz-2' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->hifz_achievement === 2 ? 'border-blue-500 bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-blue-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">جيد</button>

                                    <button type="button" @click="syncingTask = 'hifz-1'; await $wire.saveAchievement({{ $currentDay->id }}, 'hifz', 1); syncingTask = null"
                                        :disabled="syncingTask !== null"
                                        class="px-1 py-2.5 md:p-3 rounded-lg md:rounded-xl border-2 transition-colors font-bold text-center text-xs md:text-base disabled:opacity-50 disabled:cursor-wait"
                                        :class="syncingTask === 'hifz-1' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->hifz_achievement === 1 ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-amber-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">مقبول</button>

                                    <button type="button" @click="syncingTask = 'hifz-null'; await $wire.saveAchievement({{ $currentDay->id }}, 'hifz', null); syncingTask = null"
                                        :disabled="syncingTask !== null"
                                        class="px-1 py-2.5 md:p-3 rounded-lg md:rounded-xl border-2 transition-colors font-bold text-center text-xs md:text-base disabled:opacity-50 disabled:cursor-wait"
                                        :class="syncingTask === 'hifz-null' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->hifz_achievement === null ? 'border-red-500 bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-red-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">لم يسمع</button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Review Section --}}
                    @if($currentDay->plan->plan_type === 'review' || $currentDay->plan->plan_type === 'hifz_review')
                        <div class="bg-emerald-50/50 dark:bg-emerald-500/5 rounded-xl border border-emerald-100 dark:border-emerald-500/20 p-3.5 md:p-5 space-y-3 md:space-y-5">
                            <div>
                                <flux:heading size="lg" class="text-emerald-600 dark:text-emerald-400 mb-1 md:mb-2">{{ __('المراجعة') }}</flux:heading>
                                <p class="text-zinc-700 dark:text-zinc-300 font-medium text-base md:text-lg leading-snug md:leading-relaxed">
                                    {{ $currentDay->formatRange('review') ?? 'لا يوجد نص محدد' }}
                                </p>
                                @php
                                    $rLinks = [];
                                    $rFrom = $currentDay->reviewFromAyah;
                                    $rTo = $currentDay->reviewToAyah;
                                    if ($rFrom && $rTo) {
                                        if ($rFrom->surah_id === $rTo->surah_id) {
                                            $rLinks[] = [
                                                'name' => $rFrom->surah->name_arabic,
                                                'url' => 'https://quran.com/ar/' . $rFrom->surah->number . '/' . $rFrom->verse_number . '-' . $rTo->verse_number,
                                            ];
                                        } else {
                                            $low = min($rFrom->surah_id, $rTo->surah_id);
                                            $high = max($rFrom->surah_id, $rTo->surah_id);
                                            $direction = $rFrom->surah_id <= $rTo->surah_id ? 'asc' : 'desc';
                                            $surahs = $allSurahs->filter(fn($s) => $s->id >= $low && $s->id <= $high);
                                            if ($direction === 'desc') {
                                                $surahs = $surahs->sortByDesc('id');
                                            } else {
                                                $surahs = $surahs->sortBy('id');
                                            }
                                            foreach ($surahs as $s) {
                                                $from = $s->id === $rFrom->surah_id ? $rFrom->verse_number : 1;
                                                $to = $s->id === $rTo->surah_id ? $rTo->verse_number : $s->verses_count;
                                                $rLinks[] = [
                                                    'name' => $s->name_arabic,
                                                    'url' => 'https://quran.com/ar/' . $s->number . '/' . $from . '-' . $to,
                                                ];
                                            }
                                        }
                                    } elseif ($rFrom) {
                                        $rLinks[] = [
                                            'name' => $rFrom->surah->name_arabic,
                                            'url' => 'https://quran.com/ar/' . $rFrom->surah->number . '/' . $rFrom->verse_number . '-' . $rFrom->surah->verses_count,
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
                                <flux:label class="mb-2 md:mb-3 text-xs md:text-sm font-semibold">{{ __('تقييم الإنجاز (التسميع)') }}</flux:label>
                                {{-- Four across even on a phone: two rows of grade buttons pushed the review card off-screen. --}}
                                <div class="grid grid-cols-4 gap-1.5 md:gap-2">
                                    <button type="button" @click="syncingTask = 'review-3'; await $wire.saveAchievement({{ $currentDay->id }}, 'review', 3); syncingTask = null"
                                        :disabled="syncingTask !== null"
                                        class="px-1 py-2.5 md:p-3 rounded-lg md:rounded-xl border-2 transition-colors font-bold text-center text-xs md:text-base disabled:opacity-50 disabled:cursor-wait"
                                        :class="syncingTask === 'review-3' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->review_achievement === 3 ? 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-700 dark:text-green-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-green-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">ممتاز</button>

                                    <button type="button" @click="syncingTask = 'review-2'; await $wire.saveAchievement({{ $currentDay->id }}, 'review', 2); syncingTask = null"
                                        :disabled="syncingTask !== null"
                                        class="px-1 py-2.5 md:p-3 rounded-lg md:rounded-xl border-2 transition-colors font-bold text-center text-xs md:text-base disabled:opacity-50 disabled:cursor-wait"
                                        :class="syncingTask === 'review-2' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->review_achievement === 2 ? 'border-blue-500 bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-blue-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">جيد</button>

                                    <button type="button" @click="syncingTask = 'review-1'; await $wire.saveAchievement({{ $currentDay->id }}, 'review', 1); syncingTask = null"
                                        :disabled="syncingTask !== null"
                                        class="px-1 py-2.5 md:p-3 rounded-lg md:rounded-xl border-2 transition-colors font-bold text-center text-xs md:text-base disabled:opacity-50 disabled:cursor-wait"
                                        :class="syncingTask === 'review-1' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->review_achievement === 1 ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-amber-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">مقبول</button>

                                    <button type="button" @click="syncingTask = 'review-null'; await $wire.saveAchievement({{ $currentDay->id }}, 'review', null); syncingTask = null"
                                        :disabled="syncingTask !== null"
                                        class="px-1 py-2.5 md:p-3 rounded-lg md:rounded-xl border-2 transition-colors font-bold text-center text-xs md:text-base disabled:opacity-50 disabled:cursor-wait"
                                        :class="syncingTask === 'review-null' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $currentDay->review_achievement === null ? 'border-red-500 bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300' : 'border-zinc-200 dark:border-zinc-700 hover:border-red-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' }}'">لم يسمع</button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endforeach
    @endif

    {{-- Ode path enrollment bar (visible when teacher may manage ode paths, or a plan exists) --}}
    @if(($perms['can_manage_ode_paths'] ?? false) || $activeOdePlan)
        <div class="mt-4 flex items-center justify-between gap-3 p-3 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
            <div class="flex items-center gap-2 min-w-0">
                <div class="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-500 shrink-0">
                    <flux:icon icon="book-open" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('مسار المنظومة') }}</div>
                    <div class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                        {{ $activeOdePlan?->path?->name ?? __('غير مُسكَّن في مسار منظومة') }}
                    </div>
                </div>
            </div>
            @if($perms['can_manage_ode_paths'] ?? false)
                <flux:button size="sm" variant="{{ $activeOdePlan ? 'ghost' : 'primary' }}" icon="{{ $activeOdePlan ? 'arrow-path' : 'plus' }}" wire:click="openPathModal('ode')">
                    {{ $activeOdePlan ? __('تغيير المسار') : __('تسكين في مسار') }}
                </flux:button>
            @endif
        </div>
    @endif

    @if($activeOdePlan && $odeDays->isNotEmpty())
        @foreach($odeDays as $odeDay)
            @php
                $hifzVerses = collect();
                if ($odeDay->from_verse_number && $odeDay->to_verse_number) {
                    $hifzVerses = $odeVerses->filter(function ($v) use ($odeDay) {
                        return $v->verse_number >= $odeDay->from_verse_number && $v->verse_number <= $odeDay->to_verse_number;
                    });
                }

                $previousHifzVerses = collect();
                if ($odeDay->from_verse_number && $odeDay->from_verse_number > 1) {
                    $previousHifzVerses = $odeVerses->filter(function ($v) use ($odeDay) {
                        return $v->verse_number < $odeDay->from_verse_number;
                    })->values()->slice(-5)->values();
                }

                $reviewVerses = collect();
                if ($odeDay->review_from_verse_number && $odeDay->review_to_verse_number) {
                    $reviewVerses = $odeVerses->filter(function ($v) use ($odeDay) {
                        return $v->verse_number >= $odeDay->review_from_verse_number && $v->verse_number <= $odeDay->review_to_verse_number;
                    });
                }

                $previousReviewVerses = collect();
                if ($odeDay->review_from_verse_number && $odeDay->review_from_verse_number > 1) {
                    $previousReviewVerses = $odeVerses->filter(function ($v) use ($odeDay) {
                        return $v->verse_number < $odeDay->review_from_verse_number;
                    })->values()->slice(-5)->values();
                }
            @endphp
            <div wire:key="ode-day-card-container-{{ $odeDay->id }}" x-show="activeOdeDayId == {{ $odeDay->id }}" class="mt-4" x-data="{ showHifzModal: false, showReviewModal: false, prevHifzCount: 0, prevReviewCount: 0 }">

                {{-- Unified Card --}}
                <flux:card x-data="{ syncingTask: null }" class="flex flex-col border-zinc-200 dark:border-zinc-700 min-h-[350px] h-full justify-between" wire:loading.class="opacity-50 pointer-events-none transition-opacity duration-200" wire:target="saveOdeAchievement">

                    {{-- Day navigation --}}
                    <div class="flex items-center justify-between mb-6 border-b border-zinc-100 dark:border-zinc-800 pb-4 shrink-0">
                        <flux:button type="button" @click="prevOdeDay()" x-bind:disabled="!hasPrevOdeDay()" icon="chevron-right" variant="subtle" size="sm">
                            {{ __('اليوم السابق') }}
                        </flux:button>

                        <div class="text-center">
                            <div class="font-bold text-lg leading-none">{{ $odeDay->day_name }}</div>
                            <div class="text-zinc-500 text-sm dir-ltr mt-1 leading-none">{{ $odeDay->date->format('Y/m/d') }}</div>
                            <div class="text-xs text-indigo-600 dark:text-indigo-400 mt-1.5 font-semibold">
                                {{ __('تسميع المنظومة') }}: {{ $activeOdePlan->path->ode->name }}
                            </div>
                        </div>

                        <flux:button type="button" @click="nextOdeDay()" x-bind:disabled="!hasNextOdeDay()" icon-trailing="chevron-left" variant="subtle" size="sm">
                            {{ __('اليوم التالي') }}
                        </flux:button>
                    </div>

                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Ode Hifz --}}
                        @if ($odeDay->from_verse_number && $odeDay->to_verse_number)
                            <div class="bg-indigo-50/50 dark:bg-indigo-500/5 rounded-xl border border-indigo-100 dark:border-indigo-500/20 p-3 md:p-4 space-y-2.5 md:space-y-4 flex flex-col justify-between">
                                <div>
                                    <flux:heading size="sm" class="text-indigo-600 dark:text-indigo-400 mb-1">{{ __('حفظ المنظومة') }}</flux:heading>
                                    <p class="text-zinc-700 dark:text-zinc-300 font-bold text-sm">
                                        {{ $odeDay->formatOdeRange('hifz') }}
                                    </p>
                                    @if ($odeDay->hifz_achievement !== null && $odeDay->hifz_graded_at)
                                        <div class="text-xs text-indigo-600/70 dark:text-indigo-400/70 font-semibold mt-1">
                                            {{ __('تاريخ الإنجاز') }}: {{ \Carbon\Carbon::parse($odeDay->hifz_graded_at)->format('Y-m-d') }}
                                        </div>
                                    @endif
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                {{-- No margin of its own: the parent's space-y already separates it. --}}
                                <div>
                                    <flux:button type="button" @click="showHifzModal = true" icon="book-open" variant="subtle" class="w-full justify-center">
                                        {{ __('إظهار نص المنظومة') }}
                                    </flux:button>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div class="shrink-0">
                                    <flux:label class="mb-2 text-xs font-semibold">{{ __('تقييم الحفظ') }}</flux:label>
                                    {{-- Four across even on a phone, as in the Quran card. --}}
                                    <div class="grid grid-cols-4 gap-1.5">
                                        @foreach([
                                                [
                                                    'val' => 3,
                                                    'lbl' => 'ممتاز',
                                                    'activeClass' => 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-700 dark:text-green-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-green-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => 2,
                                                    'lbl' => 'جيد',
                                                    'activeClass' => 'border-blue-500 bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-blue-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => 1,
                                                    'lbl' => 'مقبول',
                                                    'activeClass' => 'border-amber-500 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-amber-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => null,
                                                    'lbl' => 'لم يسمع',
                                                    'activeClass' => 'border-red-500 bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-red-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ]
                                            ] as $item)
                                                @php
                                                    $val = $item['val'];
                                                    $lbl = $item['lbl'];
                                                    $activeClass = $item['activeClass'];
                                                    $inactiveClass = $item['inactiveClass'];
                                                @endphp
                                                <button type="button" 
                                                    @click="syncingTask = 'ode-hifz-{{ $val ?? 'null' }}'; await $wire.saveOdeAchievement({{ $odeDay->id }}, 'hifz', {{ $val ?? 'null' }}); syncingTask = null"
                                                    :disabled="syncingTask !== null"
                                                    class="py-2.5 rounded-lg border-2 transition-all font-bold text-center text-xs disabled:opacity-50 disabled:cursor-wait"
                                                    :class="syncingTask === 'ode-hifz-{{ $val ?? 'null' }}' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $odeDay->hifz_achievement === $val ? $activeClass : $inactiveClass }}'">
                                                    {{ $lbl }}
                                                </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Ode Review --}}
                        @if ($odeDay->review_from_verse_number && $odeDay->review_to_verse_number)
                            <div class="bg-emerald-50/50 dark:bg-emerald-500/5 rounded-xl border border-emerald-100 dark:border-emerald-500/20 p-3 md:p-4 space-y-2.5 md:space-y-4 flex flex-col justify-between">
                                <div>
                                    <flux:heading size="sm" class="text-emerald-600 dark:text-emerald-400 mb-1">{{ __('مراجعة المنظومة') }}</flux:heading>
                                    <p class="text-zinc-700 dark:text-zinc-300 font-bold text-sm">
                                        {{ $odeDay->formatOdeRange('review') }}
                                    </p>
                                    @if ($odeDay->review_achievement !== null && $odeDay->review_graded_at)
                                        <div class="text-xs text-emerald-600/70 dark:text-emerald-400/70 font-semibold mt-1">
                                            {{ __('تاريخ الإنجاز') }}: {{ \Carbon\Carbon::parse($odeDay->review_graded_at)->format('Y-m-d') }}
                                        </div>
                                    @endif
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div>
                                    <flux:button type="button" @click="showReviewModal = true" icon="book-open" variant="subtle" class="w-full justify-center">
                                        {{ __('إظهار نص المنظومة') }}
                                    </flux:button>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div class="shrink-0">
                                    <flux:label class="mb-2 text-xs font-semibold">{{ __('تقييم المراجعة') }}</flux:label>
                                    {{-- Four across even on a phone, as in the Quran card. --}}
                                    <div class="grid grid-cols-4 gap-1.5">
                                        @foreach([
                                                [
                                                    'val' => 3,
                                                    'lbl' => 'ممتاز',
                                                    'activeClass' => 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-700 dark:text-green-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-green-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => 2,
                                                    'lbl' => 'جيد',
                                                    'activeClass' => 'border-blue-500 bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-blue-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => 1,
                                                    'lbl' => 'مقبول',
                                                    'activeClass' => 'border-amber-500 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-amber-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => null,
                                                    'lbl' => 'لم يسمع',
                                                    'activeClass' => 'border-red-500 bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-red-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ]
                                            ] as $item)
                                                @php
                                                    $val = $item['val'];
                                                    $lbl = $item['lbl'];
                                                    $activeClass = $item['activeClass'];
                                                    $inactiveClass = $item['inactiveClass'];
                                                @endphp
                                                <button type="button" 
                                                    @click="syncingTask = 'ode-review-{{ $val ?? 'null' }}'; await $wire.saveOdeAchievement({{ $odeDay->id }}, 'review', {{ $val ?? 'null' }}); syncingTask = null"
                                                    :disabled="syncingTask !== null"
                                                    class="py-2.5 rounded-lg border-2 transition-all font-bold text-center text-xs disabled:opacity-50 disabled:cursor-wait"
                                                    :class="syncingTask === 'ode-review-{{ $val ?? 'null' }}' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $odeDay->review_achievement === $val ? $activeClass : $inactiveClass }}'">
                                                    {{ $lbl }}
                                                </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Full-Screen Modal for Hifz --}}
                    @if ($odeDay->from_verse_number && $odeDay->to_verse_number)
                        <div x-show="showHifzModal" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-4"
                             class="fixed inset-0 z-50 bg-white dark:bg-zinc-950 flex flex-col w-full h-full"
                             x-cloak>

                            {{-- Modal Header --}}
                            <div class="flex items-center justify-between p-5 border-b border-zinc-100 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-900/50 shrink-0">
                                <div>
                                    <h3 class="font-bold text-lg text-zinc-900 dark:text-white leading-tight">
                                        {{ __('حفظ المنظومة') }}
                                    </h3>
                                    <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1 font-semibold">
                                        {{ $activeOdePlan->path->ode->name }} ({{ $odeDay->formatOdeRange('hifz') }})
                                    </p>
                                </div>
                                <button type="button" @click="showHifzModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors">
                                    <flux:icon icon="x-mark" class="size-5" />
                                </button>
                            </div>

                            {{-- Modal Content (Scrollable) --}}
                            <div class="flex-1 overflow-y-auto p-6 md:p-12 space-y-4 bg-zinc-50/30 dark:bg-zinc-950/30">
                                <div class="max-w-4xl mx-auto space-y-4">
                                    {{-- Previous Verses Button --}}
                                    @if($previousHifzVerses->isNotEmpty())
                                        <div x-show="prevHifzCount < {{ $previousHifzVerses->count() }}" class="flex justify-center mb-4 shrink-0">
                                            <flux:button type="button" @click="prevHifzCount++" icon="arrow-up" variant="subtle" class="w-full sm:w-auto font-bold border border-zinc-200 dark:border-zinc-700">
                                                {{ __('إظهار البيت السابق') }}
                                            </flux:button>
                                        </div>
                                    @endif

                                    {{-- Previous Verses (Dimmed) --}}
                                    @foreach($previousHifzVerses as $index => $verse)
                                        <div x-show="prevHifzCount >= {{ $previousHifzVerses->count() - $index }}"
                                             x-cloak
                                             class="flex items-start gap-4 p-4 md:p-6 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100/60 dark:border-zinc-800/40 shadow-sm opacity-40 hover:opacity-80 transition-opacity">
                                            <span class="shrink-0 flex items-center justify-center size-8 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500 font-extrabold text-sm shadow-sm">
                                                {{ $verse->verse_number }}
                                            </span>
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8 text-base md:text-xl leading-relaxed">
                                                <div class="font-semibold text-zinc-500 dark:text-zinc-400 text-right pr-4 border-r-4 border-zinc-300 dark:border-zinc-600 font-serif">
                                                    {{ $verse->sadr }}
                                                </div>
                                                <div class="font-semibold text-zinc-400 dark:text-zinc-500 text-right pl-4 md:border-r md:border-dashed md:border-zinc-200 md:dark:border-zinc-800 font-serif">
                                                    {{ $verse->ajuz }}
                                                    <span class="text-xs font-sans text-zinc-400 dark:text-zinc-600 mr-2">({{ __('سابق') }})</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach

                                    {{-- Current Hifz Verses --}}
                                    @foreach($hifzVerses as $verse)
                                        <div class="flex items-start gap-4 p-4 md:p-6 bg-white dark:bg-zinc-900 rounded-2xl border border-indigo-100/60 dark:border-indigo-950/40 shadow-sm hover:shadow-md transition-shadow">
                                            <span class="shrink-0 flex items-center justify-center size-8 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-extrabold text-sm shadow-sm">
                                                {{ $verse->verse_number }}
                                            </span>
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8 text-base md:text-xl leading-relaxed">
                                                <div class="font-semibold text-zinc-800 dark:text-zinc-100 text-right pr-4 border-r-4 border-indigo-500 dark:border-indigo-400 font-serif">
                                                    {{ $verse->sadr }}
                                                </div>
                                                <div class="font-semibold text-zinc-700 dark:text-zinc-300 text-right pl-4 md:border-r md:border-dashed md:border-zinc-200 md:dark:border-zinc-800 font-serif">
                                                    {{ $verse->ajuz }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Modal Footer --}}
                            <div class="p-4 border-t border-zinc-100 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-900/50 flex justify-end shrink-0">
                                <flux:button type="button" @click="showHifzModal = false; prevHifzCount = 0" variant="ghost">
                                    {{ __('إغلاق') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    {{-- Full-Screen Modal for Review --}}
                    @if ($odeDay->review_from_verse_number && $odeDay->review_to_verse_number)
                        <div x-show="showReviewModal" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-4"
                             class="fixed inset-0 z-50 bg-white dark:bg-zinc-900 flex flex-col w-full h-full"
                             x-cloak>

                            {{-- Modal Header --}}
                            <div class="flex items-center justify-between p-5 border-b border-zinc-100 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-900/50 shrink-0">
                                <div>
                                    <h3 class="font-bold text-lg text-zinc-900 dark:text-white leading-tight">
                                        {{ __('مراجعة المنظومة') }}
                                    </h3>
                                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-semibold">
                                        {{ $activeOdePlan->path->ode->name }} ({{ $odeDay->formatOdeRange('review') }})
                                    </p>
                                </div>
                                <button type="button" @click="showReviewModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors">
                                    <flux:icon icon="x-mark" class="size-5" />
                                </button>
                            </div>

                            {{-- Modal Content (Scrollable) --}}
                            <div class="flex-1 overflow-y-auto p-6 md:p-12 space-y-4 bg-zinc-50/30 dark:bg-zinc-950/30">
                                <div class="max-w-4xl mx-auto space-y-4">
                                    {{-- Previous Verses Button --}}
                                    @if($previousReviewVerses->isNotEmpty())
                                        <div x-show="prevReviewCount < {{ $previousReviewVerses->count() }}" class="flex justify-center mb-4 shrink-0">
                                            <flux:button type="button" @click="prevReviewCount++" icon="arrow-up" variant="subtle" class="w-full sm:w-auto font-bold border border-zinc-200 dark:border-zinc-700">
                                                {{ __('إظهار البيت السابق') }}
                                            </flux:button>
                                        </div>
                                    @endif

                                    {{-- Previous Verses (Dimmed) --}}
                                    @foreach($previousReviewVerses as $index => $verse)
                                        <div x-show="prevReviewCount >= {{ $previousReviewVerses->count() - $index }}"
                                             x-cloak
                                             class="flex items-start gap-4 p-4 md:p-6 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100/60 dark:border-zinc-800/40 shadow-sm opacity-40 hover:opacity-80 transition-opacity">
                                            <span class="shrink-0 flex items-center justify-center size-8 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500 font-extrabold text-sm shadow-sm">
                                                {{ $verse->verse_number }}
                                            </span>
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8 text-base md:text-xl leading-relaxed">
                                                <div class="font-semibold text-zinc-500 dark:text-zinc-400 text-right pr-4 border-r-4 border-zinc-300 dark:border-zinc-600 font-serif">
                                                    {{ $verse->sadr }}
                                                </div>
                                                <div class="font-semibold text-zinc-400 dark:text-zinc-500 text-right pl-4 md:border-r md:border-dashed md:border-zinc-200 md:dark:border-zinc-800 font-serif">
                                                    {{ $verse->ajuz }}
                                                    <span class="text-xs font-sans text-zinc-400 dark:text-zinc-600 mr-2">({{ __('سابق') }})</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach

                                    {{-- Current Review Verses --}}
                                    @foreach($reviewVerses as $verse)
                                        <div class="flex items-start gap-4 p-4 md:p-6 bg-white dark:bg-zinc-900 rounded-2xl border border-emerald-100/60 dark:border-emerald-950/40 shadow-sm hover:shadow-md transition-shadow">
                                            <span class="shrink-0 flex items-center justify-center size-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-extrabold text-sm shadow-sm">
                                                {{ $verse->verse_number }}
                                            </span>
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8 text-base md:text-xl leading-relaxed">
                                                <div class="font-semibold text-zinc-800 dark:text-zinc-100 text-right pr-4 border-r-4 border-emerald-500 dark:border-emerald-400 font-serif">
                                                    {{ $verse->sadr }}
                                                </div>
                                                <div class="font-semibold text-zinc-700 dark:text-zinc-300 text-right pl-4 md:border-r md:border-dashed md:border-zinc-200 md:dark:border-zinc-800 font-serif">
                                                    {{ $verse->ajuz }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Modal Footer --}}
                            <div class="p-4 border-t border-zinc-100 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-900/50 flex justify-end shrink-0">
                                <flux:button type="button" @click="showReviewModal = false; prevReviewCount = 0" variant="ghost">
                                    {{ __('إغلاق') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </flux:card>
            </div>
        @endforeach
    @elseif($activeOdePlan && $odeDays->isEmpty())
        <flux:card class="mt-4 border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-2 mb-6 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon icon="book-open" class="size-5 text-indigo-500" />
                <div>
                    <flux:heading size="md" class="font-bold text-zinc-900 dark:text-white">
                        {{ __('تسميع المنظومة') }}: {{ $activeOdePlan->path->ode->name }}
                    </flux:heading>
                </div>
            </div>
            <div class="flex flex-col items-center justify-center p-6 bg-zinc-50/50 dark:bg-zinc-900/50 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-center">
                <flux:icon icon="document-text" class="size-8 text-zinc-300 dark:text-zinc-600 mb-2" />
                <p class="text-zinc-500 dark:text-zinc-400 text-sm">
                    {{ __('لا توجد أبيات مجدولة لهذه المنظومة.') }}
                </p>
            </div>
        </flux:card>
    @endif

    {{-- Hadith path enrollment bar (visible when teacher may manage hadith paths, or a plan exists) --}}
    @if(($perms['can_manage_hadith_paths'] ?? false) || $activeHadithPlan)
        <div class="mt-4 flex items-center justify-between gap-3 p-3 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
            <div class="flex items-center gap-2 min-w-0">
                <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-500 shrink-0">
                    <flux:icon icon="document-text" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('مسار الحديث') }}</div>
                    <div class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                        {{ $activeHadithPlan?->path?->name ?? __('غير مُسكَّن في مسار حديث') }}
                    </div>
                </div>
            </div>
            @if($perms['can_manage_hadith_paths'] ?? false)
                <flux:button size="sm" variant="{{ $activeHadithPlan ? 'ghost' : 'primary' }}" icon="{{ $activeHadithPlan ? 'arrow-path' : 'plus' }}" wire:click="openPathModal('hadith')">
                    {{ $activeHadithPlan ? __('تغيير المسار') : __('تسكين في مسار') }}
                </flux:button>
            @endif
        </div>
    @endif

    {{-- Hadith plans daily rendering --}}
    @if($activeHadithPlan && $hadithDays->isNotEmpty())
        @foreach($hadithDays as $hadithDay)
            @php
                $hifzHadiths = collect();
                if ($hadithDay->memorize_type === 'hadiths' && $hadithDay->from_hadith_id && $hadithDay->to_hadith_id) {
                    $startIdx = $allHadiths->search(fn($h) => $h->id == $hadithDay->from_hadith_id);
                    $endIdx = $allHadiths->search(fn($h) => $h->id == $hadithDay->to_hadith_id);
                    if ($startIdx !== false && $endIdx !== false) {
                        $hifzHadiths = $allHadiths->slice($startIdx, $endIdx - $startIdx + 1);
                    }
                } elseif ($hadithDay->memorize_type === 'lines' && $hadithDay->from_hadith_id) {
                    $hadith = $allHadiths->first(fn($h) => $h->id == $hadithDay->from_hadith_id);
                    if ($hadith) {
                        $hifzHadiths->push($hadith);
                    }
                }

                $reviewHadiths = collect();
                if ($hadithDay->memorize_type === 'hadiths' && $hadithDay->review_from_hadith_id && $hadithDay->review_to_hadith_id) {
                    $startIdx = $allHadiths->search(fn($h) => $h->id == $hadithDay->review_from_hadith_id);
                    $endIdx = $allHadiths->search(fn($h) => $h->id == $hadithDay->review_to_hadith_id);
                    if ($startIdx !== false && $endIdx !== false) {
                        $reviewHadiths = $allHadiths->slice($startIdx, $endIdx - $startIdx + 1);
                    }
                } elseif ($hadithDay->memorize_type === 'lines' && $hadithDay->review_from_hadith_id) {
                    $hadith = $allHadiths->first(fn($h) => $h->id == $hadithDay->review_from_hadith_id);
                    if ($hadith) {
                        $reviewHadiths->push($hadith);
                    }
                }

                $firstHifzHadith = $hifzHadiths->first();
                $firstHifzIdx = $firstHifzHadith ? $allHadiths->search(fn($h) => $h->id == $firstHifzHadith->id) : false;
                $previousHifzHadiths = $firstHifzIdx !== false && $firstHifzIdx > 0
                    ? $allHadiths->slice(0, $firstHifzIdx)->values()
                    : collect();

                $firstReviewHadith = $reviewHadiths->first();
                $firstReviewIdx = $firstReviewHadith ? $allHadiths->search(fn($h) => $h->id == $firstReviewHadith->id) : false;
                $previousReviewHadiths = $firstReviewIdx !== false && $firstReviewIdx > 0
                    ? $allHadiths->slice(0, $firstReviewIdx)->values()
                    : collect();
            @endphp
            <div wire:key="hadith-day-card-container-{{ $hadithDay->id }}" x-show="activeHadithDayId == {{ $hadithDay->id }}" class="mt-4" x-data="{ showHadithHifzModal: false, showHadithReviewModal: false, hifzPrevCount: 0, reviewPrevCount: 0 }">

                {{-- Unified Card --}}
                <flux:card x-data="{ syncingTask: null }" class="flex flex-col border-zinc-200 dark:border-zinc-700 min-h-[350px] h-full justify-between" wire:loading.class="opacity-50 pointer-events-none transition-opacity duration-200" wire:target="saveHadithAchievement">

                    {{-- Day navigation --}}
                    <div class="flex items-center justify-between mb-6 border-b border-zinc-100 dark:border-zinc-800 pb-4 shrink-0">
                        <flux:button type="button" @click="prevHadithDay()" x-bind:disabled="!hasPrevHadithDay()" icon="chevron-right" variant="subtle" size="sm">
                            {{ __('اليوم السابق') }}
                        </flux:button>

                        <div class="text-center">
                            <div class="font-bold text-lg leading-none">{{ $hadithDay->day_name }}</div>
                            <div class="text-zinc-500 text-sm dir-ltr mt-1 leading-none">{{ $hadithDay->date->format('Y/m/d') }}</div>
                            <div class="text-xs text-rose-600 dark:text-rose-400 mt-1.5 font-semibold">
                                {{ __('تسميع الحديث') }}: {{ $hadithDay->fromHadith->name ?? '' }}
                            </div>
                        </div>

                        <flux:button type="button" @click="nextHadithDay()" x-bind:disabled="!hasNextHadithDay()" icon-trailing="chevron-left" variant="subtle" size="sm">
                            {{ __('اليوم التالي') }}
                        </flux:button>
                    </div>

                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Hadith Hifz --}}
                        @if ($hadithDay->from_hadith_id)
                            <div class="bg-indigo-50/50 dark:bg-indigo-500/5 rounded-xl border border-indigo-100 dark:border-indigo-500/20 p-3 md:p-4 space-y-2.5 md:space-y-4 flex flex-col justify-between">
                                <div>
                                    <flux:heading size="sm" class="text-indigo-600 dark:text-indigo-400 mb-1">{{ __('حفظ الحديث') }}</flux:heading>
                                    <p class="text-zinc-700 dark:text-zinc-300 font-bold text-sm">
                                        {{ $hadithDay->formatHadithRange('hifz') }}
                                    </p>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div>
                                    <flux:button type="button" @click="showHadithHifzModal = true" icon="book-open" variant="subtle" class="w-full justify-center">
                                        {{ __('إظهار نص الحديث') }}
                                    </flux:button>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div class="shrink-0">
                                    <flux:label class="mb-2 text-xs font-semibold">{{ __('تقييم الحفظ') }}</flux:label>
                                    {{-- Four across even on a phone, as in the Quran card. --}}
                                    <div class="grid grid-cols-4 gap-1.5">
                                        @foreach([
                                                [
                                                    'val' => 3,
                                                    'lbl' => 'ممتاز',
                                                    'activeClass' => 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-700 dark:text-green-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-green-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => 2,
                                                    'lbl' => 'جيد',
                                                    'activeClass' => 'border-blue-500 bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-blue-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => 1,
                                                    'lbl' => 'مقبول',
                                                    'activeClass' => 'border-amber-500 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-amber-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => null,
                                                    'lbl' => 'لم يسمع',
                                                    'activeClass' => 'border-red-500 bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-red-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ]
                                            ] as $item)
                                                @php
                                                    $val = $item['val'];
                                                    $lbl = $item['lbl'];
                                                    $activeClass = $item['activeClass'];
                                                    $inactiveClass = $item['inactiveClass'];
                                                @endphp
                                                <button type="button" 
                                                    @click="syncingTask = 'hadith-hifz-{{ $val ?? 'null' }}'; await $wire.saveHadithAchievement({{ $hadithDay->id }}, 'hifz', {{ $val ?? 'null' }}); syncingTask = null"
                                                    :disabled="syncingTask !== null"
                                                    class="py-2.5 rounded-lg border-2 transition-all font-bold text-center text-xs disabled:opacity-50 disabled:cursor-wait"
                                                    :class="syncingTask === 'hadith-hifz-{{ $val ?? 'null' }}' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $hadithDay->hifz_achievement === $val ? $activeClass : $inactiveClass }}'">
                                                    {{ $lbl }}
                                                </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Hadith Review --}}
                        @if ($hadithDay->review_from_hadith_id)
                            <div class="bg-emerald-50/50 dark:bg-emerald-500/5 rounded-xl border border-emerald-100 dark:border-emerald-500/20 p-3 md:p-4 space-y-2.5 md:space-y-4 flex flex-col justify-between">
                                <div>
                                    <flux:heading size="sm" class="text-emerald-600 dark:text-emerald-400 mb-1">{{ __('مراجعة الحديث') }}</flux:heading>
                                    <p class="text-zinc-700 dark:text-zinc-300 font-bold text-sm">
                                        {{ $hadithDay->formatHadithRange('review') }}
                                    </p>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div>
                                    <flux:button type="button" @click="showHadithReviewModal = true" icon="book-open" variant="subtle" class="w-full justify-center">
                                        {{ __('إظهار نص الحديث') }}
                                    </flux:button>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div class="shrink-0">
                                    <flux:label class="mb-2 text-xs font-semibold">{{ __('تقييم المراجعة') }}</flux:label>
                                    {{-- Four across even on a phone, as in the Quran card. --}}
                                    <div class="grid grid-cols-4 gap-1.5">
                                        @foreach([
                                                [
                                                    'val' => 3,
                                                    'lbl' => 'ممتاز',
                                                    'activeClass' => 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-700 dark:text-green-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-green-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => 2,
                                                    'lbl' => 'جيد',
                                                    'activeClass' => 'border-blue-500 bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-blue-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => 1,
                                                    'lbl' => 'مقبول',
                                                    'activeClass' => 'border-amber-500 bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-amber-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ],
                                                [
                                                    'val' => null,
                                                    'lbl' => 'لم يسمع',
                                                    'activeClass' => 'border-red-500 bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300',
                                                    'inactiveClass' => 'border-zinc-200 dark:border-zinc-700 hover:border-red-200 bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300'
                                                ]
                                            ] as $item)
                                                @php
                                                    $val = $item['val'];
                                                    $lbl = $item['lbl'];
                                                    $activeClass = $item['activeClass'];
                                                    $inactiveClass = $item['inactiveClass'];
                                                @endphp
                                                <button type="button" 
                                                    @click="syncingTask = 'hadith-review-{{ $val ?? 'null' }}'; await $wire.saveHadithAchievement({{ $hadithDay->id }}, 'review', {{ $val ?? 'null' }}); syncingTask = null"
                                                    :disabled="syncingTask !== null"
                                                    class="py-2.5 rounded-lg border-2 transition-all font-bold text-center text-xs disabled:opacity-50 disabled:cursor-wait"
                                                    :class="syncingTask === 'hadith-review-{{ $val ?? 'null' }}' ? 'border-zinc-300 bg-zinc-200 text-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 scale-105' : '{{ $hadithDay->review_achievement === $val ? $activeClass : $inactiveClass }}'">
                                                    {{ $lbl }}
                                                </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Hadith Full-Screen Hifz Modal --}}
                    @if ($hadithDay->from_hadith_id)
                        <div x-show="showHadithHifzModal" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-4"
                             class="fixed inset-0 z-50 bg-white dark:bg-zinc-900 flex flex-col w-full h-full"
                             x-cloak>

                             {{-- Modal Header --}}
                             <div class="flex items-center justify-between p-5 border-b border-zinc-100 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-900/50 shrink-0">
                                 <div>
                                     <h3 class="font-bold text-lg text-zinc-900 dark:text-white leading-tight">
                                         {{ __('حفظ الحديث') }}
                                     </h3>
                                     <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1 font-semibold">
                                         {{ $hadithDay->fromHadith?->name }} ({{ $hadithDay->formatHadithRange('hifz') }})
                                     </p>
                                 </div>
                                 <button type="button" @click="showHadithHifzModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors">
                                     <flux:icon icon="x-mark" class="size-5" />
                                 </button>
                             </div>

                             {{-- Modal Content (Scrollable) --}}
                             <div class="flex-1 overflow-y-auto p-6 md:p-12 space-y-6 bg-zinc-50/30 dark:bg-zinc-950/30">
                                 <div class="max-w-4xl mx-auto space-y-8 text-right">
                                      {{-- Previous Hadiths Button --}}
                                      @if($previousHifzHadiths->isNotEmpty())
                                          <div x-show="hifzPrevCount < {{ $previousHifzHadiths->count() }}" class="flex justify-center mb-8 shrink-0">
                                              <flux:button type="button" @click="hifzPrevCount++" icon="arrow-up" variant="subtle" class="w-full sm:w-auto font-bold">
                                                  {{ __('إظهار الحديث السابق') }}
                                              </flux:button>
                                          </div>
                                      @endif

                                      {{-- Previous Hadiths (Dimmed) --}}
                                      @foreach($previousHifzHadiths as $index => $hadith)
                                          @php
                                            $currentHadithLines = $hadith->lines;
                                          @endphp
                                          <div x-show="hifzPrevCount >= {{ $previousHifzHadiths->count() - $index }}"
                                               x-cloak
                                               class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 border-r-4 border-r-zinc-400 dark:border-r-zinc-500 shadow-sm p-5 md:p-7 space-y-4 opacity-60 hover:opacity-100 transition-opacity duration-200">
                                               {{-- Hadith Header (Name), only needed when several hadiths share this day --}}
                                               @if ($previousHifzHadiths->count() > 1)
                                                   <div class="flex items-center gap-2 text-base font-bold text-zinc-600 dark:text-zinc-300">
                                                       <span class="truncate">{{ $hadith->name }}</span>
                                                       <span class="shrink-0 px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('سابق') }}</span>
                                                   </div>
                                               @endif

                                               @if ($hadith->sanad)
                                                   <p class="text-lg md:text-xl text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                                       <span class="font-bold text-zinc-500 dark:text-zinc-400">{{ __('السند') }}: </span>{{ $hadith->sanad }}
                                                   </p>
                                                   <div class="border-t border-zinc-100 dark:border-zinc-800"></div>
                                               @endif

                                               @php $showLineNumbers = $currentHadithLines->count() > 1; @endphp
                                               <div class="space-y-3">
                                                   @foreach($currentHadithLines as $line)
                                                       <div class="flex items-start gap-3">
                                                           @if ($showLineNumbers)
                                                               <span class="shrink-0 mt-2 flex items-center justify-center size-7 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-bold text-xs">{{ $line->line_number }}</span>
                                                           @endif
                                                           <p class="flex-1 text-xl md:text-2xl font-bold text-zinc-700 dark:text-zinc-200 leading-loose font-serif">{{ $line->text }}</p>
                                                       </div>
                                                   @endforeach
                                               </div>

                                               @if ($hadith->ruling)
                                                   <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                                                       <p class="text-sm text-zinc-500 dark:text-zinc-400"><span class="font-bold text-zinc-600 dark:text-zinc-300">{{ __('حكم الحديث') }}: </span>{{ $hadith->ruling }}</p>
                                                   </div>
                                               @endif
                                          </div>
                                      @endforeach

                                     @foreach($hifzHadiths as $hadith)
                                         @php
                                            $currentHadithLines = $hadith->lines;
                                            if ($hadithDay->memorize_type === 'lines') {
                                                $currentHadithLines = $currentHadithLines->filter(function ($l) use ($hadithDay) {
                                                    return $l->line_number >= $hadithDay->from_line_number && $l->line_number <= $hadithDay->to_line_number;
                                                });
                                            }
                                            $showLineNumbers = $currentHadithLines->count() > 1;
                                         @endphp
                                         <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 border-r-4 border-r-indigo-500 dark:border-r-indigo-400 shadow-sm p-5 md:p-7 space-y-4">
                                             {{-- Hadith Header (Name), only needed when several hadiths share this day --}}
                                             @if ($hifzHadiths->count() > 1)
                                                 <div class="text-base font-bold text-indigo-600 dark:text-indigo-400 truncate">
                                                     {{ $hadith->name }}
                                                 </div>
                                             @endif

                                             @if ($hadith->sanad)
                                                 <p class="text-lg md:text-xl text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                                     <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ __('السند') }}: </span>{{ $hadith->sanad }}
                                                 </p>
                                                 <div class="border-t border-zinc-100 dark:border-zinc-800"></div>
                                             @endif

                                             <div class="space-y-3">
                                                 @foreach($currentHadithLines as $line)
                                                     <div class="flex items-start gap-3">
                                                         @if ($showLineNumbers)
                                                             <span class="shrink-0 mt-2 flex items-center justify-center size-7 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-200 font-bold text-xs">{{ $line->line_number }}</span>
                                                         @endif
                                                         <p class="flex-1 text-xl md:text-2xl font-bold text-zinc-900 dark:text-zinc-50 leading-loose font-serif">{{ $line->text }}</p>
                                                     </div>
                                                 @endforeach
                                             </div>

                                             @if ($hadith->ruling)
                                                 <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                                                     <p class="text-sm text-zinc-500 dark:text-zinc-400"><span class="font-bold text-indigo-600 dark:text-indigo-400">{{ __('حكم الحديث') }}: </span>{{ $hadith->ruling }}</p>
                                                 </div>
                                             @endif
                                         </div>
                                     @endforeach
                                 </div>
                             </div>

                             {{-- Modal Footer --}}
                             <div class="p-4 border-t border-zinc-100 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-900/50 flex justify-end shrink-0">
                                 <flux:button type="button" @click="showHadithHifzModal = false" variant="ghost">
                                     {{ __('إغلاق') }}
                                 </flux:button>
                             </div>
                        </div>
                    @endif

                    {{-- Hadith Full-Screen Review Modal --}}
                    @if ($hadithDay->review_from_hadith_id)
                        <div x-show="showHadithReviewModal" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-4"
                             class="fixed inset-0 z-50 bg-white dark:bg-zinc-900 flex flex-col w-full h-full"
                             x-cloak>

                             {{-- Modal Header --}}
                             <div class="flex items-center justify-between p-5 border-b border-zinc-100 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-900/50 shrink-0">
                                 <div>
                                     <h3 class="font-bold text-lg text-zinc-900 dark:text-white leading-tight">
                                         {{ __('مراجعة الحديث') }}
                                     </h3>
                                     <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-semibold">
                                         {{ $hadithDay->reviewFromHadith?->name ?? $hadithDay->fromHadith?->name }} ({{ $hadithDay->formatHadithRange('review') }})
                                     </p>
                                 </div>
                                 <button type="button" @click="showHadithReviewModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors">
                                     <flux:icon icon="x-mark" class="size-5" />
                                 </button>
                             </div>

                             {{-- Modal Content (Scrollable) --}}
                             <div class="flex-1 overflow-y-auto p-6 md:p-12 space-y-6 bg-zinc-50/30 dark:bg-zinc-900/30">
                                 <div class="max-w-4xl mx-auto space-y-8 text-right">
                                     {{-- Previous Hadiths Button --}}
                                     @if($previousReviewHadiths->isNotEmpty())
                                         <div x-show="reviewPrevCount < {{ $previousReviewHadiths->count() }}" class="flex justify-center mb-8 shrink-0">
                                             <flux:button type="button" @click="reviewPrevCount++" icon="arrow-up" variant="subtle" class="w-full sm:w-auto font-bold">
                                                 {{ __('إظهار الحديث السابق') }}
                                             </flux:button>
                                         </div>
                                     @endif

                                     {{-- Previous Hadiths (Dimmed) --}}
                                     @foreach($previousReviewHadiths as $index => $hadith)
                                         @php
                                            $currentHadithLines = $hadith->lines;
                                         @endphp
                                         <div x-show="reviewPrevCount >= {{ $previousReviewHadiths->count() - $index }}"
                                              x-cloak
                                              class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 border-r-4 border-r-zinc-400 dark:border-r-zinc-500 shadow-sm p-5 md:p-7 space-y-4 opacity-60 hover:opacity-100 transition-opacity duration-200">
                                              {{-- Hadith Header (Name), only needed when several hadiths share this day --}}
                                              @if ($previousReviewHadiths->count() > 1)
                                                  <div class="flex items-center gap-2 text-base font-bold text-zinc-600 dark:text-zinc-300">
                                                      <span class="truncate">{{ $hadith->name }}</span>
                                                      <span class="shrink-0 px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('سابق') }}</span>
                                                  </div>
                                              @endif

                                              @if ($hadith->sanad)
                                                  <p class="text-lg md:text-xl text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                                      <span class="font-bold text-zinc-500 dark:text-zinc-400">{{ __('السند') }}: </span>{{ $hadith->sanad }}
                                                  </p>
                                                  <div class="border-t border-zinc-100 dark:border-zinc-800"></div>
                                              @endif

                                              @php $showLineNumbers = $currentHadithLines->count() > 1; @endphp
                                              <div class="space-y-3">
                                                  @foreach($currentHadithLines as $line)
                                                      <div class="flex items-start gap-3">
                                                          @if ($showLineNumbers)
                                                              <span class="shrink-0 mt-2 flex items-center justify-center size-7 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-bold text-xs">{{ $line->line_number }}</span>
                                                          @endif
                                                          <p class="flex-1 text-xl md:text-2xl font-bold text-zinc-700 dark:text-zinc-200 leading-loose font-serif">{{ $line->text }}</p>
                                                      </div>
                                                  @endforeach
                                              </div>

                                              @if ($hadith->ruling)
                                                  <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                                                      <p class="text-sm text-zinc-500 dark:text-zinc-400"><span class="font-bold text-zinc-600 dark:text-zinc-300">{{ __('حكم الحديث') }}: </span>{{ $hadith->ruling }}</p>
                                                  </div>
                                              @endif
                                         </div>
                                     @endforeach

                                     @foreach($reviewHadiths as $hadith)
                                         @php
                                            $currentHadithLines = $hadith->lines;
                                            if ($hadithDay->memorize_type === 'lines') {
                                                $currentHadithLines = $currentHadithLines->filter(function ($l) use ($hadithDay) {
                                                    return $l->line_number >= $hadithDay->review_from_line_number && $l->line_number <= $hadithDay->review_to_line_number;
                                                });
                                            }
                                            $showLineNumbers = $currentHadithLines->count() > 1;
                                         @endphp
                                         <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 border-r-4 border-r-emerald-500 dark:border-r-emerald-400 shadow-sm p-5 md:p-7 space-y-4">
                                             {{-- Hadith Header (Name), only needed when several hadiths share this day --}}
                                             @if ($reviewHadiths->count() > 1)
                                                 <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 truncate">
                                                     {{ $hadith->name }}
                                                 </div>
                                             @endif

                                             @if ($hadith->sanad)
                                                 <p class="text-lg md:text-xl text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                                     <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ __('السند') }}: </span>{{ $hadith->sanad }}
                                                 </p>
                                                 <div class="border-t border-zinc-100 dark:border-zinc-800"></div>
                                             @endif

                                             <div class="space-y-3">
                                                 @foreach($currentHadithLines as $line)
                                                     <div class="flex items-start gap-3">
                                                         @if ($showLineNumbers)
                                                             <span class="shrink-0 mt-2 flex items-center justify-center size-7 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-200 font-bold text-xs">{{ $line->line_number }}</span>
                                                         @endif
                                                         <p class="flex-1 text-xl md:text-2xl font-bold text-zinc-900 dark:text-zinc-50 leading-loose font-serif">{{ $line->text }}</p>
                                                     </div>
                                                 @endforeach
                                             </div>

                                             @if ($hadith->ruling)
                                                 <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                                                     <p class="text-sm text-zinc-500 dark:text-zinc-400"><span class="font-bold text-emerald-600 dark:text-emerald-400">{{ __('حكم الحديث') }}: </span>{{ $hadith->ruling }}</p>
                                                 </div>
                                             @endif
                                         </div>
                                     @endforeach
                                 </div>
                             </div>

                             {{-- Modal Footer --}}
                             <div class="p-4 border-t border-zinc-100 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-900/50 flex justify-end shrink-0">
                                 <flux:button type="button" @click="showHadithReviewModal = false" variant="ghost">
                                     {{ __('إغلاق') }}
                                 </flux:button>
                             </div>
                        </div>
                    @endif
                </flux:card>
            </div>
        @endforeach
    @elseif($activeHadithPlan && $hadithDays->isEmpty())
        <flux:card class="mt-4 border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-2 mb-6 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon icon="document-text" class="size-5 text-rose-500" />
                <div>
                    <flux:heading size="md" class="font-bold text-zinc-900 dark:text-white">
                        {{ __('تسميع مسار الحديث') }}: {{ $activeHadithPlan->path?->name }}
                    </flux:heading>
                </div>
            </div>
            <div class="flex flex-col items-center justify-center p-6 bg-zinc-50/50 dark:bg-zinc-900/50 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-center">
                <flux:icon icon="document-text" class="size-8 text-zinc-300 dark:text-zinc-600 mb-2" />
                <p class="text-zinc-500 dark:text-zinc-400 text-sm">
                    {{ __('لا توجد أسطر مجدولة لهذا المسار.') }}
                </p>
            </div>
        </flux:card>
    @endif

    {{-- Gamification track enrollment bar (visible when teacher may manage tracks) --}}
    @if($perms['can_manage_gamification_tracks'] ?? false)
        <div class="mt-4 flex items-center justify-between gap-3 p-3 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
            <div class="flex items-center gap-2 min-w-0">
                <div class="p-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-500 shrink-0">
                    <flux:icon icon="trophy" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('مسارات التلعيب') }}</div>
                    @if($currentTracks->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($currentTracks as $t)
                                <flux:badge size="sm" color="amber">{{ $t->leaderboard?->title }} — {{ $t->name }}</flux:badge>
                            @endforeach
                        </div>
                    @else
                        <div class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('غير مُسكَّن في أي مسار تلعيب') }}</div>
                    @endif
                </div>
            </div>
            <flux:button size="sm" variant="{{ $currentTracks->isNotEmpty() ? 'ghost' : 'primary' }}" icon="{{ $currentTracks->isNotEmpty() ? 'arrow-path' : 'plus' }}" wire:click="openTrackModal">
                {{ $currentTracks->isNotEmpty() ? __('تعديل') : __('تسكين في مسار') }}
            </flux:button>
        </div>
    @endif

    @if($sPlans->isEmpty())
        <div class="mt-4 flex flex-col items-center justify-center p-8 bg-zinc-50/50 dark:bg-zinc-900/50 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl text-center">
            <flux:icon icon="document-text" class="size-12 text-zinc-300 dark:text-zinc-600 mb-3" />
            <flux:heading size="md" class="text-zinc-500 dark:text-zinc-400 mb-1">{{ __('لا توجد خطة قرآنية لهذا الطالب') }}</flux:heading>
            <p class="text-zinc-400 dark:text-zinc-500 text-sm max-w-sm mb-4">{{ __('أنشئ خطة قرآنية للطالب للبدء بتقييم التسميع والمراجعة القرآنية.') }}</p>
            <flux:button href="{{ route('teacher.plan-creator', ['studentId' => $student->id]) }}" variant="primary" icon="plus">{{ __('خطة قرآنية جديدة') }}</flux:button>
        </div>
    @endif

    {{-- Path enrollment modal (hadith / ode) --}}
    <flux:modal wire:model="showPathModal" class="md:w-[500px]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">
                    {{ $pathModalType === 'ode' ? __('تسكين في مسار منظومة') : __('تسكين في مسار حديث') }}
                </flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('اختر المسار الذي تريد تسكين الطالب فيه. سيتم إيقاف المسار الحالي (إن وجد) والاحتفاظ بإنجازاته.') }}
                </flux:text>
            </div>

            <flux:select wire:model="selectedPathId">
                <flux:select.option value="">{{ __('بدون مسار (إلغاء التسكين)') }}</flux:select.option>
                @foreach($availablePaths as $p)
                    <flux:select.option value="{{ $p['id'] }}">{{ $p['name'] }}{{ $p['text'] ? ' — '.$p['text'] : '' }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showPathModal', false)">{{ __('إلغاء') }}</flux:button>
                <flux:button variant="primary" wire:click="enrollInPath">{{ __('حفظ') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Gamification track enrollment modal --}}
    <flux:modal wire:model="showTrackModal" class="md:w-[550px]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('تسكين الطالب في مسارات التلعيب') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('يمكن تسكين الطالب في مسار واحد لكل مسابقة نشطة تغطي حلقته.') }}
                </flux:text>
            </div>

            @forelse($availableCompetitions as $competition)
                <div class="space-y-1.5">
                    <flux:label>{{ $competition['title'] }}</flux:label>
                    <flux:select wire:model="trackSelections.{{ $competition['id'] }}">
                        <flux:select.option value="">{{ __('بدون مسار') }}</flux:select.option>
                        @foreach($competition['tracks'] as $track)
                            <flux:select.option value="{{ $track['id'] }}">{{ $track['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @empty
                <flux:callout variant="secondary" icon="information-circle">
                    {{ __('لا توجد مسابقات تلعيب نشطة فيها مسارات تغطي حلقة هذا الطالب.') }}
                </flux:callout>
            @endforelse

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showTrackModal', false)">{{ __('إلغاء') }}</flux:button>
                @if(count($availableCompetitions) > 0)
                    <flux:button variant="primary" wire:click="saveTrackEnrollment">{{ __('حفظ') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
