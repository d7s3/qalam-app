<?php

use App\Models\Circle;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentSelfProgramEntry;
use App\Services\SelfProgramService;
use App\Support\Scope;
use App\Support\SelfProgramUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * One student's record of the self programme, week by week.
 *
 * The programme could already be settled after its week had closed — recorded
 * under today, because that is when it was done, while still counting towards
 * the week it belonged to. What nothing said was that it had been late: the
 * figure went in and the week quietly filled, so a boy a fortnight behind and a
 * boy who kept up read identically on every screen there was.
 *
 * The same page answers for the student himself and for the offices above him.
 * What differs is not the reading but the depth: he is shown what he did and
 * what of it was late; they are shown the entries underneath — each day, its
 * amount, and whose hand wrote it — because correcting a figure means knowing
 * where it came from.
 */
new class extends Component
{
    /** Which guard is looking. Decides whose record, and how deep. */
    public string $role = 'student';

    public ?int $studentId = null;

    public ?int $stageId = null;

    public string $search = '';

    /** The week whose entries are unfolded, if one is. */
    public ?int $openWeekId = null;

    public function mount(): void
    {
        if ($this->role === 'student') {
            $this->studentId = Auth::guard('student')->id();

            return;
        }

        $this->stageId = $this->stages->first()?->id;
        $this->studentId ??= $this->students->first()?->id;
    }

    /** Whether the entries underneath are shown — the offices, not the student. */
    public function detailed(): bool
    {
        return $this->role !== 'student';
    }

    /** The programmes this viewer may look into. */
    #[Computed]
    public function stages(): Collection
    {
        if ($this->role === 'student' || $this->role === 'guardian') {
            return collect();
        }

        $reach = Scope::forRole($this->role)->stageIds();

        return $reach === null
            ? Stage::orderBy('name')->get()
            : Stage::whereIn('id', $reach)->orderBy('name')->get();
    }

    /**
     * The students this viewer may look at.
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function students(): Collection
    {
        if ($this->role === 'student') {
            return collect();
        }

        $query = Student::query()->with('circle')->orderBy('name');

        if ($this->role === 'guardian') {
            $query->where('guardian_id', Auth::guard('guardian')->id());
        } elseif ($this->role === 'teacher') {
            $query->whereIn('circle_id', Scope::forRole('teacher')->user()?->circles()->pluck('circles.id') ?? collect());
        } elseif ($this->stageId) {
            // A student's stage is his circle's; the direct column only holds
            // for students not yet placed in one.
            $query->where(fn ($q) => $q
                ->whereIn('circle_id', Circle::where('stage_id', $this->stageId)->pluck('id'))
                ->orWhere(fn ($sub) => $sub->whereNull('circle_id')->where('stage_id', $this->stageId)));
        } else {
            return collect();
        }

        if ($this->search !== '') {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        return $query->limit(200)->get();
    }

    /**
     * The student being read, refused to anyone he is not within reach of.
     *
     * The student's own id is taken from his guard rather than from the screen,
     * so nothing he sends can point it at somebody else.
     */
    #[Computed]
    public function student(): ?Student
    {
        if ($this->role === 'student') {
            return Auth::guard('student')->user();
        }

        if (! $this->studentId) {
            return null;
        }

        return $this->students->firstWhere('id', $this->studentId)
            ?? abort(403);
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function record(): array
    {
        $student = $this->student;

        return $student ? app(SelfProgramService::class)->weeklyRecord($student) : [];
    }

    /** What the record adds up to, so the page opens on the answer. */
    #[Computed]
    public function summary(): array
    {
        $record = collect($this->record);
        $closed = $record->where('closed', true);

        return [
            'weeks' => $record->count(),
            'done' => $closed->where('overall', '>=', 100)->count(),
            'late' => $record->filter(fn ($row) => $row['late'] > 0)->count(),
            'average' => $closed->isEmpty() ? null : round($closed->avg('overall'), 1),
        ];
    }

    public function openWeek(?int $weekId): void
    {
        $this->openWeekId = $this->openWeekId === $weekId ? null : $weekId;
    }

    /**
     * The entries underneath one week — the offices only.
     *
     * @return Collection<int, StudentSelfProgramEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        $student = $this->student;

        if (! $this->detailed() || ! $student || ! $this->openWeekId) {
            return collect();
        }

        $week = collect($this->record)->firstWhere('week.id', $this->openWeekId)['week'] ?? null;

        if (! $week instanceof SelfProgramWeek) {
            return collect();
        }

        return StudentSelfProgramEntry::where('student_id', $student->id)
            ->whereIn('self_program_item_id', $week->items->pluck('id'))
            ->with(['recordedBy', 'item'])
            ->orderBy('entry_date')
            ->get();
    }
};
?>

<div class="space-y-5" dir="rtl">
    @if ($this->detailed())
        <div class="flex flex-wrap items-end gap-3">
            @if ($this->stages->isNotEmpty())
                <flux:field>
                    <flux:label>{{ __('البرنامج') }}</flux:label>
                    <flux:select wire:model.live="stageId">
                        @foreach ($this->stages as $stage)
                            <flux:select.option value="{{ $stage->id }}">{{ $stage->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            @endif

            <flux:field class="flex-1 min-w-52">
                <flux:label>{{ __('الطالب') }}</flux:label>
                <flux:select wire:model.live="studentId">
                    @foreach ($this->students as $one)
                        <flux:select.option value="{{ $one->id }}">{{ $one->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field class="w-52">
                <flux:label>{{ __('بحث') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="{{ __('اسم الطالب') }}" />
            </flux:field>
        </div>
    @endif

    @if (! $this->student)
        <flux:card class="text-center text-zinc-500 py-10">{{ __('لا طالب لعرض سجلّه.') }}</flux:card>
    @elseif (empty($this->record))
        <flux:card class="text-center text-zinc-500 py-10">{{ __('لا أسابيع مكتوبةً بعد.') }}</flux:card>
    @else
        @php $sum = $this->summary; @endphp

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <flux:card class="py-4">
                <div class="text-xs text-zinc-500">{{ __('الأسابيع') }}</div>
                <div class="text-2xl font-bold tabular-nums">{{ $sum['weeks'] }}</div>
            </flux:card>
            <flux:card class="py-4">
                <div class="text-xs text-zinc-500">{{ __('أسابيع أُتمّت') }}</div>
                <div class="text-2xl font-bold tabular-nums text-emerald-600">{{ $sum['done'] }}</div>
            </flux:card>
            <flux:card class="py-4">
                <div class="text-xs text-zinc-500">{{ __('أسابيع فيها تأخّر') }}</div>
                <div class="text-2xl font-bold tabular-nums {{ $sum['late'] > 0 ? 'text-amber-600' : '' }}">{{ $sum['late'] }}</div>
            </flux:card>
            <flux:card class="py-4">
                <div class="text-xs text-zinc-500">{{ __('متوسّط المنقضية') }}</div>
                <div class="text-2xl font-bold tabular-nums">{{ $sum['average'] === null ? '—' : $sum['average'].'%' }}</div>
            </flux:card>
        </div>

        <div class="space-y-3">
            @foreach ($this->record as $row)
                @php
                    $week = $row['week'];
                    $isOpen = $this->openWeekId === $week->id;
                @endphp
                <flux:card wire:key="w-{{ $week->id }}" class="p-0 overflow-hidden">
                    <div class="p-4 flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-3">
                            <div>
                                <div class="font-bold">{{ __('الأسبوع') }} {{ $week->week_number }}</div>
                                <div class="text-xs text-zinc-500">
                                    <x-hijri-date :date="$week->starts_on" /> — <x-hijri-date :date="$week->ends_on" />
                                </div>
                            </div>
                            @if (! $row['closed'])
                                <flux:badge color="sky" size="sm">{{ __('جارٍ') }}</flux:badge>
                            @endif
                            @if ($row['late'] > 0)
                                {{-- يُحتسب له، ويُقال له إنه تأخّر. --}}
                                <flux:badge color="amber" size="sm" icon="clock">
                                    {{ __('أُنجز متأخّراً') }}
                                </flux:badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 min-w-40">
                                <div class="flex-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                    <div class="h-full rounded-full {{ $row['overall'] >= 100 ? 'bg-emerald-500' : ($row['late'] > 0 ? 'bg-amber-400' : 'bg-maroon') }}"
                                        style="width: {{ min(100, $row['overall']) }}%"></div>
                                </div>
                                <span class="text-xs tabular-nums w-10">{{ $row['overall'] }}%</span>
                            </div>
                            @if ($this->detailed())
                                <flux:button size="xs" variant="ghost" wire:click="openWeek({{ $week->id }})"
                                    icon="{{ $isOpen ? 'chevron-up' : 'chevron-down' }}">
                                    {{ __('التفاصيل') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($row['tracks'] as $track)
                            <div class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-sm">
                                <div class="flex items-center gap-2 min-w-40">
                                    <flux:icon :icon="$track['item']->track?->icon() ?? 'sparkles'" class="size-4 text-zinc-400" />
                                    <span class="font-medium">{{ $track['item']->track?->label() }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if ($track['late'] > 0)
                                        <span class="text-[11px] text-amber-600 dark:text-amber-400">
                                            {{ __('منه متأخّر:') }} {{ SelfProgramUnit::say($track['late'], $track['item']->displayUnit()) }}
                                        </span>
                                    @endif
                                    <span class="tabular-nums text-zinc-500">
                                        {{ SelfProgramUnit::say($track['done'], $track['item']->displayUnit()) }}
                                        <span class="text-zinc-300 dark:text-zinc-600">/</span>
                                        {{ SelfProgramUnit::say($track['target'], $track['item']->displayUnit()) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($this->detailed() && $isOpen)
                        <div class="bg-zinc-50 dark:bg-zinc-900/40 border-t border-zinc-100 dark:border-zinc-800 p-4">
                            @forelse ($this->entries as $entry)
                                @php $late = $entry->entry_date->copy()->startOfDay()->greaterThan($week->ends_on->copy()->startOfDay()); @endphp
                                <div class="flex flex-wrap items-center justify-between gap-2 py-1.5 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="tabular-nums text-zinc-500 w-24">{{ $entry->entry_date->format('Y-m-d') }}</span>
                                        <span class="font-medium">{{ $entry->item?->track?->label() }}</span>
                                        @if ($late)
                                            <flux:badge color="amber" size="sm">{{ __('متأخّر') }}</flux:badge>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 text-zinc-500">
                                        <span class="tabular-nums">{{ SelfProgramUnit::say((float) $entry->amount_done, $entry->item?->displayUnit()) }}</span>
                                        <span>
                                            @if ($entry->source === StudentSelfProgramEntry::SOURCE_TASMEEH)
                                                {{ __('من التسميع') }}
                                            @elseif ($entry->recordedBy)
                                                {{ $entry->recordedBy->name }}
                                            @else
                                                {{ __('الطالب') }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 text-center py-2">{{ __('لم يُسجَّل شيءٌ في هذا الأسبوع.') }}</p>
                            @endforelse
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
