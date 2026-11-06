<?php

use App\Models\Screen;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\StudentSelfProgramEntry;
use App\Support\Scope;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Where students go and what goes wrong for them, read for trouble before it
 * turns into a student who stopped coming: who has not signed in, who keeps
 * failing to, who signs in and does nothing, and who keeps meeting an error or
 * an empty page.
 */
new class extends Component
{
    /** How many days back the page reads: a week or a month. */
    public int $days = 7;

    /** Signals, as the manager reads them. */
    private const SIGNALS = [
        'self_program_no_week' => 'وجد البرنامج الذاتي بلا أسبوع',
        'self_program_empty_week' => 'وجد أسبوع البرنامج الذاتي فارغاً',
    ];

    public function updatedDays(): void
    {
        $this->days = in_array($this->days, [7, 30], true) ? $this->days : 7;
        unset($this->students, $this->events);
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function students(): Collection
    {
        return Scope::forRole('manager')->applyToStudents(Student::query())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'circle_id']);
    }

    /** @return Collection<int, StudentEvent> */
    #[Computed]
    public function events(): Collection
    {
        return StudentEvent::whereIn('student_id', $this->students->pluck('id'))
            ->where('created_at', '>=', now()->subDays($this->days))
            ->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function pageLabels(): array
    {
        return Screen::pluck('label', 'route_name')->all();
    }

    public function pageLabel(?string $route): string
    {
        return $this->pageLabels[$route] ?? ($route ?: '—');
    }

    public function with(): array
    {
        $events = $this->events;
        $byStudent = $events->groupBy('student_id');
        $since = now()->subDays($this->days);

        $recorded = StudentSelfProgramEntry::whereIn('student_id', $this->students->pluck('id'))
            ->where('created_at', '>=', $since)
            ->distinct()
            ->pluck('student_id')
            ->flip();

        // The last thing each did, ever — not just in the period read.
        $lastSeen = StudentEvent::whereIn('student_id', $this->students->pluck('id'))
            ->whereIn('kind', [StudentEvent::LOGIN, StudentEvent::PAGE])
            ->groupBy('student_id')
            ->selectRaw('student_id, max(created_at) as seen')
            ->pluck('seen', 'student_id');

        $watch = $this->students->map(function (Student $student) use ($byStudent, $recorded, $lastSeen) {
            $mine = $byStudent->get($student->id, collect());
            $came = $mine->whereIn('kind', [StudentEvent::LOGIN, StudentEvent::PAGE])->isNotEmpty();
            $reasons = [];

            if (! $came) {
                $reasons[] = isset($lastSeen[$student->id])
                    ? __('لم يدخل منذ :days يوماً', ['days' => (int) \Carbon\Carbon::parse($lastSeen[$student->id])->diffInDays(now())])
                    : __('لم يدخل منذ بدء التسجيل');
            }

            if (($failed = $mine->where('kind', StudentEvent::LOGIN_FAILED)->count()) >= 3) {
                $reasons[] = __('أخطأ في كلمة المرور :count مرات', ['count' => $failed]);
            }

            if ($came && ! isset($recorded[$student->id])) {
                $reasons[] = __('دخل ولم يسجّل إنجازاً في البرنامج الذاتي');
            }

            if (($problems = $mine->where('kind', StudentEvent::PROBLEM)->count()) > 0) {
                $reasons[] = __('واجه خطأ :count مرة', ['count' => $problems]);
            }

            foreach ($mine->where('kind', StudentEvent::SIGNAL)->countBy('detail') as $detail => $times) {
                $reasons[] = (self::SIGNALS[$detail] ?? $detail).' — '.__(':count أيام', ['count' => $times]);
            }

            return ['student' => $student, 'reasons' => $reasons, 'seen' => $lastSeen[$student->id] ?? null];
        })->filter(fn (array $row) => $row['reasons'] !== [])
            ->sortByDesc(fn (array $row) => count($row['reasons']))
            ->values();

        $cameIds = $events->whereIn('kind', [StudentEvent::LOGIN, StudentEvent::PAGE])->pluck('student_id')->unique();

        return [
            'total' => $this->students->count(),
            'came' => $cameIds->count(),
            'away' => $this->students->count() - $cameIds->count(),
            'idle' => $cameIds->reject(fn ($id) => isset($recorded[$id]))->count(),
            'troubled' => $events->whereIn('kind', [StudentEvent::PROBLEM, StudentEvent::SIGNAL])->pluck('student_id')->unique()->count(),
            'watch' => $watch,
            'pages' => $events->where('kind', StudentEvent::PAGE)->countBy('route_name')->sortDesc()->take(8),
            'problems' => $events->where('kind', StudentEvent::PROBLEM)->sortByDesc('created_at')->take(15)->values(),
            'names' => $this->students->pluck('name', 'id'),
            'firstRecord' => StudentEvent::min('created_at'),
        ];
    }
};
?>

<div class="space-y-6 p-4 sm:p-6 lg:p-8" dir="rtl">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold">{{ __('سلوك الطلاب') }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ __('من دخل ومن غاب، ومن يواجه مشكلة، لتتدارك قبل أن يترك الطالب المنصة.') }}
                @if ($firstRecord)
                    {{ __('التسجيل بدأ في') }} <x-hijri-date :date="$firstRecord" />.
                @endif
            </flux:subheading>
        </div>
        <flux:radio.group wire:model.live="days" variant="segmented" size="sm">
            <flux:radio :value="7" :label="__('آخر أسبوع')" />
            <flux:radio :value="30" :label="__('آخر شهر')" />
        </flux:radio.group>
    </div>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4" data-behavior-figures>
        @foreach ([
            [__('دخلوا'), $came, __('من :total طالباً نشطاً', ['total' => $total]), 'text-emerald-600'],
            [__('لم يدخلوا'), $away, __('في هذه المدة'), 'text-zinc-900 dark:text-white'],
            [__('دخلوا ولم يسجّلوا'), $idle, __('في البرنامج الذاتي'), 'text-amber-600'],
            [__('واجهوا مشكلة'), $troubled, __('خطأ أو صفحة فارغة'), 'text-red-600'],
        ] as [$label, $value, $caption, $color])
            <flux:card class="space-y-1">
                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
                <div class="text-3xl font-black tabular-nums {{ $color }}">{{ $value }}</div>
                <div class="text-[11px] text-zinc-400">{{ $caption }}</div>
            </flux:card>
        @endforeach
    </div>

    <flux:card class="space-y-3">
        <flux:heading size="lg">{{ __('يحتاجون متابعة') }}</flux:heading>
        @forelse ($watch as $row)
            <div wire:key="w-{{ $row['student']->id }}" class="flex flex-wrap items-start justify-between gap-3 border-t border-zinc-100 pt-3 first-of-type:border-0 first-of-type:pt-0 dark:border-zinc-800" data-watch-row>
                <div class="min-w-0">
                    <div class="font-bold text-sm text-zinc-900 dark:text-white">{{ $row['student']->name }}</div>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        @foreach ($row['reasons'] as $reason)
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ $reason }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="text-xs text-zinc-400 shrink-0">
                    @if ($row['seen'])
                        {{ __('آخر دخول') }}: <x-hijri-date :date="$row['seen']" />
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-zinc-500">{{ __('لا أحد يحتاج متابعة في هذه المدة.') }}</p>
        @endforelse
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('أكثر الصفحات زيارة') }}</flux:heading>
            @forelse ($pages as $route => $visits)
                <div class="flex items-center justify-between text-sm">
                    <span>{{ $this->pageLabel($route) }}</span>
                    <span class="tabular-nums text-zinc-500">{{ $visits }}</span>
                </div>
            @empty
                <p class="text-sm text-zinc-500">{{ __('لا زيارات بعد.') }}</p>
            @endforelse
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('آخر المشاكل') }}</flux:heading>
            @forelse ($problems as $problem)
                <div class="text-sm border-t border-zinc-100 pt-2 first-of-type:border-0 first-of-type:pt-0 dark:border-zinc-800">
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">{{ $names[$problem->student_id] ?? '—' }}</span>
                        <span class="text-xs text-zinc-400">{{ $problem->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="text-xs text-zinc-500">{{ $this->pageLabel($problem->route_name) }} — <span dir="ltr">{{ $problem->detail }}</span></div>
                </div>
            @empty
                <p class="text-sm text-zinc-500">{{ __('لا مشاكل مسجّلة في هذه المدة.') }}</p>
            @endforelse
        </flux:card>
    </div>
</div>
