<?php

use Livewire\Component;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use Flux\Flux;
use Livewire\Attributes\Reactive;

new class extends Component {
    public $studentId;

    public $activePlanId;

    #[Reactive]
    public $gradedAtDate;

    public $selectedPlanId;

    public function mount()
    {
        $this->selectedPlanId = $this->activePlanId;
    }

    public function selectPlan($planId)
    {
        $this->selectedPlanId = $planId;

        $plan = StudentPlan::find($planId);
        $defaultDayId = null;
        $dayIds = [];
        if ($plan) {
            $days = StudentPlanDay::where('student_plan_id', $plan->id)
                ->orderBy('date', 'asc')
                ->get();
            $dayIds = $days->pluck('id')->toArray();

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
                $defaultDayId = $oldestIncomplete->id;
            } else {
                $lastDay = $days->last();
                if ($lastDay) {
                    $defaultDayId = $lastDay->id;
                }
            }
        }

        if ($defaultDayId) {
            $this->dispatch('plan-default-day-updated', studentId: (int) $this->studentId, dayId: (int) $defaultDayId, dayIds: $dayIds);
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

        Flux::toast('تم حفظ التقييم', variant: 'success');
    }

    public function with()
    {
        $student = Student::find($this->studentId);
        $sPlans = StudentPlan::where('student_id', $this->studentId)
            ->latest()
            ->get();

        $activePlan = null;
        if ($this->selectedPlanId) {
            $activePlan = $sPlans->firstWhere('id', $this->selectedPlanId);
        }
        if (!$activePlan) {
            $activePlan = $sPlans->firstWhere('status', 'active') ?: $sPlans->first();
        }

        $days = collect();
        if ($activePlan) {
            $days = StudentPlanDay::with(['fromAyah.surah', 'toAyah.surah', 'reviewFromAyah.surah', 'reviewToAyah.surah', 'plan'])
                ->where('student_plan_id', $activePlan->id)
                ->orderBy('date', 'asc')
                ->get();
        }

        // Request-static cache to completely avoid N+1 queries for Surah loading inside loops
        static $cachedSurahs = null;
        if ($cachedSurahs === null) {
            $cachedSurahs = \App\Models\Surah::all()->keyBy('id');
        }

        return [
            'student' => $student,
            'sPlans' => $sPlans,
            'activePlan' => $activePlan,
            'days' => $days,
            'allSurahs' => $cachedSurahs,
        ];
    }
};
?>

<div>
    @if($sPlans->isNotEmpty())
        <div class="bg-white dark:bg-zinc-900 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm mb-4">
            <flux:select wire:change="selectPlan($event.target.value)" label="{{ __('الخطة القرآنية') }}" placeholder="{{ __('اختر الخطة') }}">
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

        @foreach($days as $day)
            @php $currentDay = $day; @endphp
            <flux:card wire:key="day-card-{{ $day->id }}" x-show="activeDayId[{{ $student->id }}] === {{ $day->id }}" x-data="{ syncingTask: null }" class="mt-2 border-zinc-200 dark:border-zinc-700" wire:loading.class="opacity-50 pointer-events-none transition-opacity duration-200" wire:target="saveAchievement">

                {{-- Day navigation --}}
                <div class="flex items-center justify-between mb-8 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                    <flux:button type="button" @click="prevDay({{ $student->id }})" x-bind:disabled="!hasPrevDay({{ $student->id }})" icon="chevron-right" variant="subtle" size="sm">
                        {{ __('اليوم السابق') }}
                    </flux:button>

                    <div class="text-center">
                        <div class="font-bold text-lg">{{ $day->day_name }}</div>
                        <div class="text-zinc-500 text-sm dir-ltr">{{ $day->date->format('Y/m/d') }}</div>
                    </div>

                    <flux:button type="button" @click="nextDay({{ $student->id }})" x-bind:disabled="!hasNextDay({{ $student->id }})" icon-trailing="chevron-left" variant="subtle" size="sm">
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
    @else
        <div class="flex flex-col items-center justify-center p-12 bg-zinc-50/50 dark:bg-zinc-900/50 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl text-center h-full min-h-[400px]">
            <flux:icon icon="document-text" class="size-16 text-zinc-300 dark:text-zinc-600 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400 mb-2">{{ __('لا توجد خطط لهذا الطالب') }}</flux:heading>
            <p class="text-zinc-400 dark:text-zinc-500 text-sm max-w-sm mb-6">{{ __('قم بإنشاء خطة قرآنية للطالب للبدء بتقييم التسميع والمراجعة.') }}</p>
            <flux:button href="{{ route('teacher.plan-creator', ['studentId' => $student->id]) }}" variant="primary" icon="plus">{{ __('إنشاء خطة جديدة') }}</flux:button>
        </div>
    @endif
</div>
