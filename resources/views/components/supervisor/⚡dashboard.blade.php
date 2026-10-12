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
    private const SEGMENT_COLORS = ['hifz' => '#7A2727', 'review' => '#C9A063'];

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

    /**
     * The months as stacked bars, oldest on the right, with a faint ribbon
     * carrying each layer across to the next month.
     *
     * @param  list<array{label: string, hifz: int, review: int}>  $months
     * @return array{bars: list<array>, ribbons: list<array>}
     */
    private function monthChart(array $months): array
    {
        $base = 250;
        $tallest = max(1, ...array_map(fn ($m) => $m['hifz'] + $m['review'], $months));
        $scale = 196 / $tallest;
        $xs = [472, 262, 52];
        $bars = [];

        foreach ($months as $i => $month) {
            $x = $xs[$i];
            $top = $base;
            $segments = [];

            foreach (['hifz', 'review'] as $layer) {
                $height = $month[$layer] * $scale;
                $bottom = $top;
                if ($height > 0) {
                    $top = $bottom - $height - ($segments === [] ? 0 : 4);
                    $segments[$layer] = ['y' => round($top, 1), 'h' => round($height, 1), 'rx' => min(8, $height / 2), 'color' => self::SEGMENT_COLORS[$layer], 'bottom' => $bottom];
                } else {
                    $segments[$layer] = ['y' => $top, 'h' => 0, 'rx' => 0, 'color' => self::SEGMENT_COLORS[$layer], 'bottom' => $top];
                }
            }

            $total = $month['hifz'] + $month['review'];
            $bars[] = ['x' => $x, 'cx' => $x + 58, 'segments' => $segments, 'label' => $month['label'], 'total' => $total, 'labelY' => round($top - 12, 1), 'empty' => $total === 0];
        }

        $ribbons = [];
        foreach ([[0, 1], [1, 2]] as [$right, $left]) {
            foreach (['hifz', 'review'] as $layer) {
                $a = $bars[$right]['segments'][$layer];
                $b = $bars[$left]['segments'][$layer];
                if ($a['h'] > 0 && $b['h'] > 0) {
                    $ribbons[] = [
                        'color' => self::SEGMENT_COLORS[$layer],
                        'points' => implode(' ', [
                            ($bars[$left]['x'] + 116).','.$b['y'], $bars[$right]['x'].','.$a['y'],
                            $bars[$right]['x'].','.($a['y'] + $a['h']), ($bars[$left]['x'] + 116).','.($b['y'] + $b['h']),
                        ]),
                    ];
                }
            }
        }

        return ['bars' => $bars, 'ribbons' => $ribbons];
    }

    /**
     * The statuses as arcs of a half ring.
     *
     * @param  list<array{status: StudentStatus, count: int}>  $standing
     * @return list<array{status: StudentStatus, count: int, color: string, dash: string, offset: float}>
     */
    private function ring(array $standing): array
    {
        $colors = [
            StudentStatus::Active->value => '#7A2727',
            StudentStatus::Registering->value => '#C9A063',
            StudentStatus::Suspended->value => '#A3A3A3',
            StudentStatus::Inactive->value => '#D4D4D4',
            StudentStatus::Left->value => '#E4D6B6',
        ];
        $half = M_PI * 100;
        $total = max(1, array_sum(array_column($standing, 'count')));
        $gap = count($standing) > 1 ? 2.5 : 0;
        $start = 0.0;
        $arcs = [];

        foreach ($standing as $row) {
            $length = $half * $row['count'] / $total;
            $arcs[] = $row + [
                'color' => $colors[$row['status']->value],
                'dash' => round(max(0, $length - $gap), 2).' 628.32',
                'offset' => round(-$start, 2),
            ];
            $start += $length;
        }

        return $arcs;
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

    /** A change against last week, as the pill under a figure shows it. */
    private function change(?float $now, ?float $before, bool $percent): ?array
    {
        if ($now === null || $before === null || ($percent && $before == 0.0)) {
            return null;
        }

        $value = $percent ? round(($now - $before) / $before * 100) : round($now - $before);

        return ['up' => $value >= 0, 'text' => ($value >= 0 ? '' : '−').abs($value).($percent ? '%' : '')];
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
        $lastMonth = prev($months);
        $tallestDay = max(1, ...array_column($figures['days'], 'pages'));

        $waiting = StudentPlacementRequest::pending()->with(['student', 'circle', 'requestedBy'])->whereIn('circle_id', $circleIds)->latest('id');

        return [
            'firstName' => explode(' ', trim($supervisor->name))[0] ?? '',
            'greeting' => now('Asia/Riyadh')->hour < 12 ? __('صباح الخير') : __('مساء الخير'),
            'weekLabel' => $figures['weekLabel'],
            'attendance' => $figures['attendance']['now'],
            'attendanceChange' => $this->change($figures['attendance']['now'], $figures['attendance']['before'], false),
            'activeStudents' => $active,
            'joinedThisMonth' => Student::whereIn('circle_id', $circleIds)->whereDate('joined_at', '>=', $monthStart)->count(),
            'weekPages' => $figures['pages']['now'],
            'pagesChange' => $this->change($figures['pages']['now'], $figures['pages']['before'], true),
            'monthPages' => $thisMonth['hifz'] + $thisMonth['review'],
            'monthChange' => $lastMonth ? $this->change($thisMonth['hifz'] + $thisMonth['review'], $lastMonth['hifz'] + $lastMonth['review'], true) : null,
            'chart' => $this->monthChart($months),
            'days' => collect($figures['days'])->map(fn ($day) => $day + [
                'height' => $day['pages'] > 0 ? max(8, (int) round($day['pages'] / $tallestDay * 180)) : 8,
                'best' => $day['pages'] > 0 && $day['pages'] === $tallestDay,
            ])->all(),
            'weekDayPages' => array_sum(array_column($figures['days'], 'pages')),
            'standing' => $standing,
            'ring' => $this->ring($standing),
            'activeShare' => $standing === [] ? 0 : (int) round($active / max(1, array_sum(array_column($standing, 'count'))) * 100),
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
            @if ($monthChange)
                <span dir="ltr" class="mt-2 inline-flex h-6 items-center gap-0.5 rounded-lg border px-2 text-xs font-semibold {{ $monthChange['up'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' }}">
                    {{ $monthChange['text'] }} <flux:icon :icon="$monthChange['up'] ? 'arrow-up-right' : 'arrow-down-right'" variant="micro" class="size-3.5" />
                </span>
            @endif

            <svg viewBox="0 0 640 292" class="mt-4 block h-auto w-full" role="img" aria-label="{{ __('الحفظ والمراجعة في الأشهر الثلاثة الأخيرة') }}">
                <g opacity="0.13">
                    @foreach ($chart['ribbons'] as $ribbon)
                        <polygon points="{{ $ribbon['points'] }}" fill="{{ $ribbon['color'] }}" />
                    @endforeach
                </g>
                @foreach ($chart['bars'] as $bar)
                    @if ($bar['empty'])
                        <rect x="{{ $bar['x'] }}" y="244" width="116" height="6" rx="3" fill="#F2EBDA" />
                    @endif
                    @foreach ($bar['segments'] as $segment)
                        @if ($segment['h'] > 0)
                            <rect x="{{ $bar['x'] }}" y="{{ $segment['y'] }}" width="116" height="{{ $segment['h'] }}" rx="{{ $segment['rx'] }}" fill="{{ $segment['color'] }}" />
                        @endif
                    @endforeach
                    <text x="{{ $bar['cx'] }}" y="{{ $bar['empty'] ? 232 : $bar['labelY'] }}" text-anchor="middle" font-size="15" font-weight="600" class="fill-zinc-900 dark:fill-white">{{ $bar['empty'] ? __('لا حفظ') : $bar['total'].' '.__('صفحة') }}</text>
                    <text x="{{ $bar['cx'] }}" y="282" text-anchor="middle" font-size="14" class="fill-zinc-500">{{ $bar['label'] }}</text>
                @endforeach
            </svg>
            <div class="mt-3 flex justify-center gap-6 text-sm text-zinc-600 dark:text-zinc-300">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#7A2727]"></span>{{ __('حفظ جديد') }}</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#C9A063]"></span>{{ __('مراجعة') }}</span>
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
            <div class="mt-5 flex min-h-[230px] flex-1 items-end gap-3" role="img" aria-label="{{ __('صفحات الحفظ في كل يوم من أيام هذا الأسبوع') }}">
                @foreach ($days as $day)
                    <div wire:key="day-{{ $day['date'] }}" class="flex flex-1 flex-col items-center gap-2">
                        @if ($day['best'])
                            <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $day['pages'] }}</span>
                        @endif
                        <div class="w-full rounded-[10px] {{ $day['best'] ? 'bg-gradient-to-b from-[#7A2727] to-[#C48A8A]' : ($day['upcoming'] ? 'border border-dashed border-[#E4D6B6] bg-transparent' : 'bg-[#F2EBDA] dark:bg-zinc-800') }}" style="height: {{ $day['height'] }}px"></div>
                        <span class="text-xs {{ $day['best'] ? 'font-semibold text-zinc-900 dark:text-white' : 'text-zinc-500' }}">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="flex flex-wrap gap-5">
        <section class="min-w-0 flex-[1_1_320px] rounded-2xl border border-[#EDE5D5] bg-white p-5 shadow-[0_1px_2px_rgba(63,26,25,.04)] dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-[#F7EFE0] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon icon="chart-pie" variant="mini" class="size-4" /></span>
                <h2 class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('حالات الطلاب') }}</h2>
            </div>
            @if ($standing === [])
                <p class="py-12 text-center text-zinc-500">{{ __('لا طلاب في مجموعتك بعد.') }}</p>
            @else
                <div class="mt-5 grid grid-cols-3 gap-3">
                    @foreach (array_slice($ring, 0, 3) as $arc)
                        <div class="border-s-[3px] ps-2.5" style="border-color: {{ $arc['color'] }}">
                            <div class="text-sm text-zinc-500">{{ $arc['status']->label() }}</div>
                            <div class="text-[22px] font-bold text-zinc-900 dark:text-white">{{ $arc['count'] }}</div>
                        </div>
                    @endforeach
                </div>
                <svg viewBox="0 0 240 140" class="mx-auto mt-5 block w-full max-w-[320px]" role="img" aria-label="{{ $activeShare }}% {{ __('مشاركون') }}">
                    <circle cx="120" cy="124" r="100" fill="none" stroke="#F7EFE0" stroke-width="30" stroke-dasharray="314.16 628.32" transform="rotate(180 120 124)" />
                    @foreach ($ring as $arc)
                        <circle cx="120" cy="124" r="100" fill="none" stroke="{{ $arc['color'] }}" stroke-width="30" stroke-dasharray="{{ $arc['dash'] }}" stroke-dashoffset="{{ $arc['offset'] }}" transform="rotate(180 120 124)" />
                    @endforeach
                    <text x="120" y="104" text-anchor="middle" font-size="30" font-weight="700" class="fill-zinc-900 dark:fill-white">{{ $activeShare }}%</text>
                    <text x="120" y="128" text-anchor="middle" font-size="13" class="fill-zinc-500">{{ __('مشاركون') }}</text>
                </svg>
            @endif
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
