<?php

use Livewire\Component;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\StudentOdePlan;
use App\Models\StudentOdePlanDay;
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

        $day = StudentPlanDay::find($dayId);
        if ($day) {
            \App\Services\GamificationService::syncStudentPlanDayXP($day);
        }

        Flux::toast('تم حفظ التقييم', variant: 'success');
    }

    public function saveOdeAchievement($dayId, $type, $value)
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

        StudentOdePlanDay::where('id', $dayId)->update($updateData);

        Flux::toast('تم حفظ تقييم المنظومة', variant: 'success');
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
            $studentOdePlans = StudentOdePlan::with('ode')
                ->where('student_id', $this->student->id)
                ->get();
            $activeOdePlan = $studentOdePlans->firstWhere('status', 'active');
        }

        // Selected Quranic plan
        $activePlan = null;
        if ($this->selectedPlanId) {
            $activePlan = $this->sPlans->firstWhere('id', $this->selectedPlanId);
        }
        if (!$activePlan) {
            $activePlan = $this->sPlans->firstWhere('status', 'active') ?: $this->sPlans->first();
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
                $odeDays = StudentOdePlanDay::with('plan.ode')
                    ->where('student_ode_plan_id', $activeOdePlan->id)
                    ->orderBy('date', 'asc')
                    ->get();
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

        $quranDateToDayIdMap = [];
        if ($activePlan && $days->isNotEmpty()) {
            $quranDateToDayIdMap = $days->mapWithKeys(function ($day) {
                return [$day->date->toDateString() => $day->id];
            })->toArray();
        }

        // Fetch all verses of the active Ode plan's ode
        $odeVerses = collect();
        if ($activeOdePlan) {
            $odeVerses = \App\Models\OdeVerse::where('ode_id', $activeOdePlan->ode_id)
                ->orderBy('verse_number', 'asc')
                ->get();
        }

        return [
            'student' => $this->student,
            'sPlans' => $this->sPlans,
            'activePlan' => $activePlan,
            'activeOdePlan' => $activeOdePlan,
            'odeDayForSelectedDate' => $odeDayForSelectedDate,
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
    quranDateToDayIdMap: @js($quranDateToDayIdMap),

    init() {
        console.log('⚡ Student Tasmeeh Card Init:', {
            studentId: {{ $student->id }},
            activeDayId: this.activeDayId,
            studentPlanDayIds: this.studentPlanDayIds
        });
        this.$watch('$wire.gradedAtDate', (newVal) => {
            if (this.odeDateToDayIdMap && this.odeDateToDayIdMap[newVal]) {
                this.activeOdeDayId = this.odeDateToDayIdMap[newVal];
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
                <div class="flex items-center justify-between mb-8 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                    <flux:button type="button" @click="prevDay()" x-bind:disabled="!hasPrevDay()" icon="chevron-right" variant="subtle" size="sm">
                        {{ __('اليوم السابق') }}
                    </flux:button>

                    <div class="text-center">
                        <div class="font-bold text-lg">{{ $day->day_name }}</div>
                        <div class="text-zinc-500 text-sm dir-ltr">{{ $day->date->format('Y/m/d') }}</div>
                    </div>

                    <flux:button type="button" @click="nextDay()" x-bind:disabled="!hasNextDay()" icon-trailing="chevron-left" variant="subtle" size="sm">
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
                                            $surahs = $allSurahs->filter(fn($s) => $s->id >= $low && $s->id <= $high);
                                            if ($direction === 'desc') {
                                                $surahs = $surahs->sortByDesc('id');
                                            } else {
                                                $surahs = $surahs->sortBy('id');
                                            }
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
                                            $surahs = $allSurahs->filter(fn($s) => $s->id >= $low && $s->id <= $high);
                                            if ($direction === 'desc') {
                                                $surahs = $surahs->sortByDesc('id');
                                            } else {
                                                $surahs = $surahs->sortBy('id');
                                            }
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
        @endforeach
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

                $reviewVerses = collect();
                if ($odeDay->review_from_verse_number && $odeDay->review_to_verse_number) {
                    $reviewVerses = $odeVerses->filter(function ($v) use ($odeDay) {
                        return $v->verse_number >= $odeDay->review_from_verse_number && $v->verse_number <= $odeDay->review_to_verse_number;
                    });
                }
            @endphp
            <div wire:key="ode-day-card-container-{{ $odeDay->id }}" x-show="activeOdeDayId == {{ $odeDay->id }}" class="mt-4" x-data="{ showHifzModal: false, showReviewModal: false }">
                
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
                                {{ __('تسميع المنظومة') }}: {{ $activeOdePlan->ode->name }}
                            </div>
                        </div>

                        <flux:button type="button" @click="nextOdeDay()" x-bind:disabled="!hasNextOdeDay()" icon-trailing="chevron-left" variant="subtle" size="sm">
                            {{ __('اليوم التالي') }}
                        </flux:button>
                    </div>

                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Ode Hifz --}}
                        @if ($odeDay->from_verse_number && $odeDay->to_verse_number)
                            <div class="bg-indigo-50/50 dark:bg-indigo-500/5 rounded-xl border border-indigo-100 dark:border-indigo-500/20 p-4 space-y-4 flex flex-col justify-between">
                                <div>
                                    <flux:heading size="sm" class="text-indigo-600 dark:text-indigo-400 mb-1">{{ __('حفظ المنظومة') }}</flux:heading>
                                    <p class="text-zinc-700 dark:text-zinc-300 font-bold text-sm">
                                        {{ $odeDay->formatOdeRange('hifz') }}
                                    </p>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div class="my-4">
                                    <flux:button type="button" @click="showHifzModal = true" icon="book-open" variant="subtle" class="w-full justify-center">
                                        {{ __('إظهار نص المنظومة') }}
                                    </flux:button>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div class="shrink-0">
                                    <flux:label class="mb-2 text-xs font-semibold">{{ __('تقييم الحفظ') }}</flux:label>
                                    <div class="grid grid-cols-2 gap-1.5">
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
                            <div class="bg-emerald-50/50 dark:bg-emerald-500/5 rounded-xl border border-emerald-100 dark:border-emerald-500/20 p-4 space-y-4 flex flex-col justify-between">
                                <div>
                                    <flux:heading size="sm" class="text-emerald-600 dark:text-emerald-400 mb-1">{{ __('مراجعة المنظومة') }}</flux:heading>
                                    <p class="text-zinc-700 dark:text-zinc-300 font-bold text-sm">
                                        {{ $odeDay->formatOdeRange('review') }}
                                    </p>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div class="my-4">
                                    <flux:button type="button" @click="showReviewModal = true" icon="book-open" variant="subtle" class="w-full justify-center">
                                        {{ __('إظهار نص المنظومة') }}
                                    </flux:button>
                                </div>

                                <flux:separator class="opacity-50 shrink-0" />

                                <div class="shrink-0">
                                    <flux:label class="mb-2 text-xs font-semibold">{{ __('تقييم المراجعة') }}</flux:label>
                                    <div class="grid grid-cols-2 gap-1.5">
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

                    @if (!$odeDay->from_verse_number && !$odeDay->review_from_verse_number)
                        <div class="flex-1 flex flex-col items-center justify-center p-6 bg-zinc-50/50 dark:bg-zinc-900/50 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-center">
                            <flux:icon icon="document-text" class="size-8 text-zinc-300 dark:text-zinc-600 mb-2" />
                            <p class="text-zinc-500 dark:text-zinc-400 text-sm">
                                {{ __('لا توجد أبيات مسجلة للحفظ أو المراجعة في هذا اليوم.') }}
                            </p>
                        </div>
                    @endif
                </flux:card>

                {{-- Full-Screen Modal for Hifz --}}
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
                                {{ $activeOdePlan->ode->name }} ({{ $odeDay->formatOdeRange('hifz') }})
                            </p>
                        </div>
                        <button type="button" @click="showHifzModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors">
                            <flux:icon icon="x-mark" class="size-5" />
                        </button>
                    </div>

                    {{-- Modal Content (Scrollable) --}}
                    <div class="flex-1 overflow-y-auto p-6 md:p-12 space-y-4 bg-zinc-50/30 dark:bg-zinc-950/30">
                        <div class="max-w-4xl mx-auto space-y-4">
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
                        <flux:button type="button" @click="showHifzModal = false" variant="ghost">
                            {{ __('إغلاق') }}
                        </flux:button>
                    </div>
                </div>

                {{-- Full-Screen Modal for Review --}}
                <div x-show="showReviewModal" 
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
                                {{ __('مراجعة المنظومة') }}
                            </h3>
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-semibold">
                                {{ $activeOdePlan->ode->name }} ({{ $odeDay->formatOdeRange('review') }})
                            </p>
                        </div>
                        <button type="button" @click="showReviewModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors">
                            <flux:icon icon="x-mark" class="size-5" />
                        </button>
                    </div>

                    {{-- Modal Content (Scrollable) --}}
                    <div class="flex-1 overflow-y-auto p-6 md:p-12 space-y-4 bg-zinc-50/30 dark:bg-zinc-950/30">
                        <div class="max-w-4xl mx-auto space-y-4">
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
                        <flux:button type="button" @click="showReviewModal = false" variant="ghost">
                            {{ __('إغلاق') }}
                        </flux:button>
                    </div>
                </div>
                </flux:card>

            </div>
        @endforeach
    @elseif($activeOdePlan && $odeDays->isEmpty())
        <flux:card class="mt-4 border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-2 mb-6 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon icon="book-open" class="size-5 text-indigo-500" />
                <div>
                    <flux:heading size="md" class="font-bold text-zinc-900 dark:text-white">
                        {{ __('تسميع المنظومة') }}: {{ $activeOdePlan->ode->name }}
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

    @if($sPlans->isEmpty() && !$activeOdePlan)
        <div class="flex flex-col items-center justify-center p-12 bg-zinc-50/50 dark:bg-zinc-900/50 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl text-center h-full min-h-[400px]">
            <flux:icon icon="document-text" class="size-16 text-zinc-300 dark:text-zinc-600 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400 mb-2">{{ __('لا توجد خطط لهذا الطالب') }}</flux:heading>
            <p class="text-zinc-400 dark:text-zinc-500 text-sm max-w-sm mb-6">{{ __('قم بإنشاء خطة قرآنية أو خطة منظومة للطالب للبدء بتقييم التسميع والمراجعة.') }}</p>
            <div class="flex gap-2">
                <flux:button href="{{ route('teacher.plan-creator', ['studentId' => $student->id]) }}" variant="primary" icon="plus">{{ __('خطة قرآنية جديدة') }}</flux:button>
                <flux:button href="{{ route('teacher.ode-plan-creator', ['student_id' => $student->id]) }}" variant="filled" icon="plus">{{ __('خطة منظومة جديدة') }}</flux:button>
            </div>
        </div>
    @endif
</div>

