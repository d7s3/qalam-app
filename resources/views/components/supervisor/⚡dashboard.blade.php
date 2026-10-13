<?php

use App\Models\Leaderboard;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\StudentPlacementRequest;
use App\Services\StudentPlacementService;
use App\Services\HomeFiguresService;
use App\Support\HijriDate;
use App\Support\Scope;
use App\Support\StudentStatus;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * A sheikh's home page: how his group's week went, where its memorisation
 * stands, and who is waiting for him — in that order, and nothing else.
 *
 * It was four counts of programmes, cohorts, teachers and students, and three
 * more cards below, none of which said what to do today.
 */
new class extends Component
{
    private function circleIds()
    {
        return Scope::forRole('supervisor')->reachableCircleIds();
    }

    public function acceptAsk(int $askId): void
    {
        $ask = StudentPlacementRequest::pending()->whereIn('circle_id', $this->circleIds())->findOrFail($askId);

        StudentPlacementService::approve($ask, Auth::guard('supervisor')->user());

        Flux::toast(__('انضم :student إلى :circle', ['student' => $ask->student?->name, 'circle' => trim($ask->circle?->name ?? '')]), variant: 'success');
    }

    public function declineAsk(int $askId): void
    {
        $ask = StudentPlacementRequest::pending()->whereIn('circle_id', $this->circleIds())->findOrFail($askId);

        StudentPlacementService::reject($ask, Auth::guard('supervisor')->user(), __('ليس من هذه المجموعة'));

        Flux::toast(__('أُعيد :student ليختار مجموعة أخرى', ['student' => $ask->student?->name]));
    }

    public function studentsLabel(int $count): string
    {
        return match (true) {
            $count === 0 => __('لا طلاب'),
            $count === 1 => __('طالب واحد'),
            $count === 2 => __('طالبان'),
            $count <= 10 => $count.' '.__('طلاب'),
            default => $count.' '.__('طالباً'),
        };
    }

    public function with(): array
    {
        $supervisor = Auth::guard('supervisor')->user();
        $figures = HomeFiguresService::figures($supervisor);
        $standing = HomeFiguresService::standing($supervisor);
        $circleIds = $this->circleIds();

        $monthStart = HijriDate::monthGrid(...array_values(HijriDate::yearMonthOf(now('Asia/Riyadh'))))['first'];
        $active = collect($standing)->firstWhere('status', StudentStatus::Active)['count'] ?? 0;
        $months = $figures['months'];
        $thisMonth = end($months);

        $waiting = StudentPlacementRequest::pending()->with(['student', 'circle', 'requestedBy'])->whereIn('circle_id', $circleIds)->latest('id');

        return [
            'firstName' => explode(' ', trim($supervisor->name))[0] ?? '',
            'greeting' => now('Asia/Riyadh')->hour < 12 ? __('صباح الخير') : __('مساء الخير'),
            'weekLabel' => $figures['weekLabel'],
            'attendance' => $figures['attendance']['now'],
            'attendanceChange' => HomeFiguresService::change($figures['attendance']['now'], $figures['attendance']['before'], false),
            'activeStudents' => $active,
            'joinedThisMonth' => Student::whereIn('circle_id', $circleIds)->whereDate('joined_at', '>=', $monthStart)->count(),
            'weekPages' => $figures['pages']['now'],
            'pagesChange' => HomeFiguresService::change($figures['pages']['now'], $figures['pages']['before'], true),
            'monthPages' => $thisMonth['hifz'] + $thisMonth['review'],
            'months' => $months,
            'days' => $figures['days'],
            'weekDayPages' => array_sum(array_column($figures['days'], 'pages')),
            'standing' => $standing,
            'upcomingExams' => StudentExam::whereIn('student_id', Student::whereIn('circle_id', $circleIds)->select('id'))
                ->where('status', 'pending')
                ->where('date_time', '>=', now())
                ->count(),
            'activeCompetitions' => Leaderboard::whereHas('circles', fn ($q) => $q->whereIn('circles.id', $circleIds))
                ->where('is_active', true)
                ->count(),
            'waitingCount' => (clone $waiting)->count(),
            'waiting' => $waiting->take(3)->get(),
        ];
    }
};
?>

<div class="space-y-6 p-4 sm:p-6 lg:p-8" dir="rtl">
    <x-shared.pending-surveys />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-[28px] font-bold text-zinc-900 dark:text-white">{{ __('الرئيسية') }}</h1>
            <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $greeting }} يا شيخ {{ $firstName }}، {{ __('هذه مجموعتك هذا الأسبوع.') }}</p>
            @if ($upcomingExams > 0 || $activeCompetitions > 0)
                <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    @if ($upcomingExams > 0)
                        <a href="{{ route('supervisor.held', ['screen' => 'teacher.student-exams']) }}" wire:navigate class="text-maroon hover:underline dark:text-red-secondary">{{ $upcomingExams }} {{ __('اختبارات قادمة') }}</a>
                    @endif
                    @if ($activeCompetitions > 0)
                        <a href="{{ route('supervisor.competitions') }}" wire:navigate class="text-maroon hover:underline dark:text-red-secondary">{{ $activeCompetitions }} {{ __('مسابقات نشطة') }}</a>
                    @endif
                </p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="flex h-10 items-center gap-2 rounded-xl border border-[#EDE5D5] bg-white px-3.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                <flux:icon icon="calendar" class="size-4" />{{ $weekLabel }}
            </span>
            <flux:button :href="route('supervisor.reports')" wire:navigate icon="arrow-down-tray">{{ __('التقارير') }}</flux:button>
        </div>
    </div>

    {{-- Three figures, each against last week. --}}
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <x-home.stat icon="calendar-days" :label="__('نسبة الحضور')" :value="$attendance !== null ? $attendance.'%' : '—'"
            :change="$attendanceChange" :caption="__('مقارنة بالأسبوع الماضي')" />
        <x-home.stat icon="users" :label="__('طلاب المجموعة')" :value="$activeStudents"
            :change="$joinedThisMonth > 0 ? ['up' => true, 'text' => '+'.$joinedThisMonth] : null"
            :caption="$this->studentsLabel($activeStudents).' '.__('في المجموعة').($joinedThisMonth > 0 ? '، '.__('ومن انضم هذا الشهر') : '')" />
        <x-home.stat icon="book-open" :label="__('صفحات الحفظ')" :value="$weekPages"
            :change="$pagesChange" :caption="__('هذا الأسبوع، مقارنة بالماضي')" />
    </div>

    <div class="flex flex-wrap gap-5">
        <section class="min-w-0 flex-[999_1_520px] rounded-2xl border border-[#EDE5D5] bg-white p-5 shadow-[0_1px_2px_rgba(63,26,25,.04)] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-[#F7EFE0] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon icon="chart-bar" variant="mini" class="size-4" /></span>
                <h2 class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('تقدّم الحفظ') }}</h2>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-[34px] font-bold tracking-tight text-zinc-900 dark:text-white">{{ number_format($monthPages) }}</span>
                <span class="text-zinc-500">{{ __('صفحة هذا الشهر') }}</span>
            </div>

            <div class="mt-4">
                <x-home.month-chart :months="$months" />
            </div>
        </section>

        <section class="flex min-w-0 flex-[1_1_320px] flex-col rounded-2xl border border-[#EDE5D5] bg-white p-5 shadow-[0_1px_2px_rgba(63,26,25,.04)] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-[#F7EFE0] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon icon="book-open" variant="mini" class="size-4" /></span>
                <h2 class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('الحفظ اليومي') }}</h2>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-[34px] font-bold tracking-tight text-zinc-900 dark:text-white">{{ $weekDayPages }}</span>
                <span class="text-zinc-500">{{ __('صفحة هذا الأسبوع') }}</span>
            </div>
            <x-home.week-bars :days="$days" />
        </section>
    </div>

    <div class="flex flex-wrap gap-5">
        <section class="min-w-0 flex-[1_1_320px] rounded-2xl border border-[#EDE5D5] bg-white p-5 shadow-[0_1px_2px_rgba(63,26,25,.04)] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-[#F7EFE0] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon icon="chart-pie" variant="mini" class="size-4" /></span>
                <h2 class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('حالات الطلاب') }}</h2>
            </div>
            <x-home.status-ring :standing="$standing" :empty="__('لا طلاب في مجموعتك بعد.')" />
        </section>

        <section class="min-w-0 flex-[999_1_520px] rounded-2xl border border-[#EDE5D5] bg-white p-5 shadow-[0_1px_2px_rgba(63,26,25,.04)] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-maroon/10 text-maroon dark:text-red-secondary"><flux:icon icon="user-plus" variant="mini" class="size-4" /></span>
                <h2 class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('ينتظرون التسكين') }}</h2>
                @if ($waitingCount > 0)
                    <span class="rounded-full bg-maroon/10 px-2.5 py-0.5 text-sm font-semibold text-maroon dark:text-red-secondary">{{ $waitingCount }}</span>
                @endif
                <flux:link :href="route('supervisor.students')" wire:navigate class="ms-auto text-sm">{{ __('عرض الكل') }}</flux:link>
            </div>
            @forelse ($waiting as $ask)
                <div wire:key="home-ask-{{ $ask->id }}" class="flex flex-wrap items-center gap-3 border-b border-[#F7EFE0] py-3.5 last:border-b-0 dark:border-zinc-800">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-[#EDE5D5] font-semibold text-maroon dark:border-zinc-700 dark:text-red-secondary">{{ mb_substr($ask->student?->name ?? '', 0, 1) }}</span>
                    <div class="min-w-36 flex-1">
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $ask->student?->name }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ (int) $ask->requested_by === (int) $ask->student_id ? __('اختار مجموعتك') : __('اقترحه').' '.$ask->requestedBy?->name }}
                            · {{ $ask->created_at?->locale('ar')->diffForHumans() }}
                        </div>
                    </div>
                    <flux:button size="sm" wire:click="declineAsk({{ $ask->id }})">{{ __('ليس لي') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="check" class="!bg-maroon hover:!bg-burgundy" wire:click="acceptAsk({{ $ask->id }})">{{ __('اقبله') }}</flux:button>
                </div>
            @empty
                <div class="py-10 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"><flux:icon icon="check" class="size-6" /></span>
                    <p class="mt-3 font-semibold text-zinc-800 dark:text-zinc-100">{{ __('لا أحد ينتظر') }}</p>
                    <p class="text-sm text-zinc-500">{{ __('كل من اختار مجموعتك صار فيها.') }}</p>
                </div>
            @endforelse
        </section>
    </div>
</div>
