<?php

use App\Models\OccurrenceAttendance;
use App\Models\Task;
use App\Services\DayAgendaService;
use App\Models\Compensation;
use App\Models\PeriodValue;
use App\Models\Student;
use App\Services\CompensationService;
use App\Services\EducationalLossService;
use App\Support\HijriSeasons;
use App\Support\Scope;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * One person's day, and the two things he can do about it: say he was there,
 * and say a task is done.
 *
 * Every role opens the same screen. What differs is what the day holds, and
 * that difference is `Scope`'s to decide rather than this page's.
 */
new class extends Component
{
    public string $date = '';

    /** The role this page was opened under — see the placement queue for why. */
    public string $asRole = 'student';

    public function mount(): void
    {
        $this->date = now('Asia/Riyadh')->format('Y-m-d');
        $this->asRole = Scope::resolveRole();

        // Turn the month behind him into what he owes, once on opening rather
        // than on every render. Raising is idempotent: a debt is keyed to the
        // miss it came from and a settled one is never reopened.
        if ($user = $this->reader()) {
            CompensationService::raiseFor(
                $user,
                $this->asRole,
                Carbon::parse($this->date)->subDays(30)->format('Y-m-d'),
                Carbon::parse($this->date)->subDay()->format('Y-m-d'),
            );
        }
    }

    public function shift(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->format('Y-m-d');
    }

    public function today(): void
    {
        $this->date = now('Asia/Riyadh')->format('Y-m-d');
    }

    private function reader(): ?\App\Models\User
    {
        return Scope::forRole($this->asRole)->user();
    }

    /**
     * His own answer for an appointment.
     *
     * Everyone records his own — the teacher and the supervisor and the manager
     * beside the student — so this writes for whoever is asking and nobody else.
     */
    public function record(int $eventId, string $status): void
    {
        $user = $this->reader() ?? abort(403);

        abort_unless(in_array($status, [
            OccurrenceAttendance::PRESENT,
            OccurrenceAttendance::LATE,
            OccurrenceAttendance::ABSENT,
            OccurrenceAttendance::EXCUSED,
        ], true), 422);

        $onToday = collect(DayAgendaService::occurrences($user, $this->asRole, $this->date))
            ->contains(fn (array $row) => $row['event']->id === $eventId);

        abort_unless($onToday, 404);

        OccurrenceAttendance::updateOrCreate(
            ['academic_calendar_event_id' => $eventId, 'date' => $this->date, 'user_id' => $user->id],
            ['role' => $this->asRole, 'status' => $status, 'self_recorded' => true, 'recorded_by' => $user->id],
        );

        Flux::toast(__('سُجّل حضورك'), variant: 'success');
    }

    /**
     * Settling a debt.
     *
     * He marks it himself, as he marks his own attendance — the same trust,
     * and the same record of who said so and when.
     */
    public function settle(int $compensationId): void
    {
        $user = $this->reader() ?? abort(403);

        $debt = Compensation::open()->where('user_id', $user->id)->findOrFail($compensationId);

        CompensationService::complete($debt, $user);

        Flux::toast(__('سُجّل التعويض'), variant: 'success');
    }

    public function finish(int $taskId): void
    {
        $user = $this->reader() ?? abort(403);

        $task = Task::where('assigned_to_id', $user->id)->findOrFail($taskId);

        $task->update(['status' => 'completed', 'completed_by' => $user->id]);

        Flux::toast(__('تم إنجاز المهمة'), variant: 'success');
    }

    public function with(): array
    {
        $user = $this->reader();

        if (! $user) {
            return [
                'agenda' => ['occurrences' => [], 'tasks' => collect(), 'content' => []],
                'losses' => [],
                'debts' => collect(),
                'seasons' => HijriSeasons::on($this->date),
                'values' => collect(),
            ];
        }

        return [
            'seasons' => HijriSeasons::on($this->date),
            'values' => PeriodValue::runningOn(
                $this->date,
                $user instanceof Student ? $user->stage_id : Scope::forRole($this->asRole)->stageIds()?->first(),
                $user instanceof Student ? $user->circle_id : null,
            )->get(),
            'debts' => CompensationService::openFor($user),
            'agenda' => DayAgendaService::forUser($user, $this->asRole, $this->date),
            // The week behind him, so a missed lesson is seen while it can still
            // be made good rather than in a report at the end of term.
            'losses' => EducationalLossService::formative(
                $user,
                $this->asRole,
                Carbon::parse($this->date)->subDays(7)->format('Y-m-d'),
                Carbon::parse($this->date)->subDay()->format('Y-m-d'),
            ),
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ __('يومي') }}</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
                <x-hijri-date :date="$date" />
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(-1)" />
            <flux:button size="sm" variant="ghost" wire:click="today">{{ __('اليوم') }}</flux:button>
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(1)" />
        </div>
    </div>

    @if($values->isNotEmpty())
        <flux:card class="space-y-3 border-maroon/30">
            <flux:heading size="lg">{{ __('ما نعمل عليه') }}</flux:heading>

            @foreach($values as $value)
                <div wire:key="value-{{ $value->id }}" class="space-y-1">
                    <div class="text-base font-bold text-maroon dark:text-red-secondary">{{ $value->title }}</div>
                    @if($value->practice)
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ $value->practice }}</div>
                    @endif
                    @if($value->evidence)
                        <div class="text-xs text-zinc-400">{{ $value->evidence }}</div>
                    @endif
                </div>
            @endforeach
        </flux:card>
    @endif

    @if($seasons !== [])
        <div class="rounded-xl border border-amber-200/70 dark:border-amber-900/40 bg-amber-50/60 dark:bg-amber-950/20 p-4 space-y-2">
            @foreach($seasons as $season)
                <div wire:key="season-{{ $season['key'] }}">
                    <div class="text-sm font-bold text-amber-900 dark:text-amber-200">{{ $season['label'] }}</div>
                    <div class="text-xs text-amber-800/80 dark:text-amber-300/80">{{ $season['purpose'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('المواعيد') }}</flux:heading>

        @forelse($agenda['occurrences'] as $row)
            <div class="flex items-center justify-between gap-4 py-3 border-b border-zinc-50 dark:border-zinc-800/60 last:border-0"
                 wire:key="occ-{{ $row['event']->id }}">
                <div class="min-w-0">
                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $row['event']->event_name }}</div>
                    @if($row['event']->description)
                        <div class="text-xs text-zinc-400 truncate">{{ $row['event']->description }}</div>
                    @endif
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    @foreach([
                        'present' => __('حاضر'),
                        'late' => __('متأخر'),
                        'absent' => __('غائب'),
                        'excused' => __('معذور'),
                    ] as $value => $label)
                        <button wire:click="record({{ $row['event']->id }}, '{{ $value }}')"
                            class="px-2.5 py-1.5 text-xs font-bold rounded-lg border transition-colors
                                {{ $row['status'] === $value
                                    ? 'bg-maroon text-white border-maroon'
                                    : 'border-zinc-200 dark:border-zinc-700 text-zinc-500 hover:border-maroon' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:text class="text-zinc-400">{{ __('لا مواعيد اليوم.') }}</flux:text>
        @endforelse
    </flux:card>

    @if($agenda['content'] !== [])
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('عمل اليوم') }}</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                {{ __('ما هو مقرَّر عليك اليوم، من برنامجك الذاتي ومساراتك وخطتك.') }}
            </flux:subheading>

            <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                @foreach($agenda['content'] as $item)
                    <div class="flex items-center justify-between py-2.5" wire:key="content-{{ $loop->index }}">
                        <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $item['label'] }}</span>
                        <span class="text-xs text-zinc-400">{{ $item['detail'] }}</span>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <flux:card class="space-y-3">
        <flux:heading size="lg">{{ __('مهامي') }}</flux:heading>

        @forelse($agenda['tasks'] as $task)
            <div class="flex items-center justify-between gap-4 py-3 border-b border-zinc-50 dark:border-zinc-800/60 last:border-0"
                 wire:key="task-{{ $task->id }}">
                <div class="min-w-0">
                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $task->title }}</div>
                    <div class="text-xs text-zinc-400 flex items-center gap-2">
                        @if($task->category)
                            <span>{{ $task->category->name }}</span>
                        @endif
                        @if($task->isOverdue())
                            <flux:badge color="rose" size="sm">{{ __('فات موعدها') }}</flux:badge>
                        @endif
                    </div>
                </div>

                <flux:button size="sm" variant="primary" class="!bg-emerald-600 hover:!bg-emerald-700 shrink-0"
                    wire:click="finish({{ $task->id }})">
                    {{ __('أنجزتها') }}
                </flux:button>
            </div>
        @empty
            <flux:text class="text-zinc-400">{{ __('لا مهام عليك.') }}</flux:text>
        @endforelse
    </flux:card>

    @if($debts->isNotEmpty())
        <flux:card class="space-y-3 border-amber-200 dark:border-amber-900/50">
            <div>
                <flux:heading size="lg">{{ __('عليك تعويضه') }}</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                    {{ __('يبقى معك حتى تعوّضه، ولا يُحسب على أسبوعك الحالي.') }}
                </flux:subheading>
            </div>

            <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                @foreach($debts as $debt)
                    <div class="flex items-center justify-between gap-4 py-3" wire:key="debt-{{ $debt->id }}">
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100 flex items-center gap-2">
                                {{ $debt->label }}
                                <flux:badge :color="$debt->kind === 'formative' ? 'sky' : 'violet'" size="sm">
                                    {{ $debt->kind === 'formative' ? __('لقاء') : __('عمل') }}
                                </flux:badge>
                            </div>
                            <div class="text-xs text-zinc-400 flex items-center gap-2 flex-wrap">
                                <x-hijri-date :date="$debt->original_date" />
                                @if($debt->detail)
                                    <span>· {{ $debt->detail }}</span>
                                @endif
                                @if($debt->weeksCarried() >= 1)
                                    <flux:badge color="amber" size="sm">
                                        {{ trans_choice('{1} أسبوع|{2} أسبوعان|[3,*] :count أسابيع', $debt->weeksCarried(), ['count' => $debt->weeksCarried()]) }}
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        <flux:button size="sm" variant="primary" class="!bg-maroon hover:!bg-burgundy shrink-0"
                            wire:click="settle({{ $debt->id }})">
                            {{ __('عوّضته') }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    @if($losses !== [])
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('فاتك هذا الأسبوع') }}</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                {{ __('مواعيد مضت ولم يُسجَّل حضورك فيها.') }}
            </flux:subheading>

            <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                @foreach($losses as $loss)
                    <div class="flex items-center justify-between py-2.5" wire:key="loss-{{ $loss['event']->id }}-{{ $loss['date'] }}">
                        <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $loss['event']->event_name }}</span>
                        <span class="text-xs text-zinc-400 flex items-center gap-2">
                            <x-hijri-date :date="$loss['date']" />
                            @if($loss['status'] === 'excused')
                                <flux:badge color="amber" size="sm">{{ __('بعذر') }}</flux:badge>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
