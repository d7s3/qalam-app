<?php

use App\Models\Circle;
use App\Models\CommitmentAlert;
use App\Models\Stage;
use App\Models\Student;
use App\Services\CommitmentService;
use App\Support\Scope;
use App\Support\StudentStatus;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * How closely students keep to their Quran plan and their self programme.
 *
 * Asked for by Faisal, 17 Nov 2026: a bar for each, for every group and every
 * student in it, over a period the viewer chooses; and the students below the
 * line last week, with what their teacher did about them.
 */
new class extends Component
{
    /** Which office is looking. The reach itself is asked of Scope. */
    #[Locked]
    public string $role = 'teacher';

    public string $period = 'week';

    public string $from = '';

    public string $to = '';

    public ?int $stageId = null;

    public ?int $circleId = null;

    /** @var array<int, array{action?: string, note?: string}> */
    public array $acts = [];

    public function mount(): void
    {
        if ($this->stages->count() === 1) {
            $this->stageId = $this->stages->first()->id;
        }

        if ($this->circles->count() === 1) {
            $this->circleId = $this->circles->first()->id;
        }
    }

    public function updatedStageId(): void
    {
        $this->circleId = null;
    }

    /** @return Collection<int, Stage> */
    #[Computed]
    public function stages(): Collection
    {
        return $this->role === 'teacher'
            ? collect()
            : Scope::forRole($this->role)->applyToStages(Stage::query())->orderBy('name')->get();
    }

    /** @return Collection<int, Circle> */
    #[Computed]
    public function circles(): Collection
    {
        return Circle::whereIn('id', Scope::forRole($this->role)->reachableCircleIds())
            ->when($this->stageId, fn ($q) => $q->where('stage_id', $this->stageId))
            ->orderBy('name')
            ->get();
    }

    /** @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon} */
    #[Computed]
    public function range(): array
    {
        return CommitmentService::range($this->period, $this->from, $this->to);
    }

    /**
     * Every student in the groups shown, measured once.
     *
     * @return array{students: Collection<int, Student>, rows: array<int, array<string, mixed>>}
     */
    #[Computed]
    public function measured(): array
    {
        $students = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereIn('circle_id', $this->circleId ? [$this->circleId] : $this->circles->pluck('id'))
            ->with('circle')
            ->orderBy('name')
            ->get();

        [$from, $to] = $this->range;

        return [
            'students' => $students,
            'rows' => app(CommitmentService::class)->forStudents($students, $from, $to),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function groups(): Collection
    {
        $rows = $this->measured['rows'];

        return $this->measured['students']
            ->groupBy('circle_id')
            ->map(fn (Collection $students) => [
                'circle' => $students->first()->circle,
                ...CommitmentService::summarise(array_intersect_key($rows, $students->keyBy('id')->all())),
            ])
            ->sortBy(fn (array $group) => $group['circle']->name)
            ->values();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function overall(): array
    {
        return CommitmentService::summarise($this->measured['rows']);
    }

    /** @return Collection<int, CommitmentAlert> */
    #[Computed]
    public function alerts(): Collection
    {
        return CommitmentAlert::query()
            ->whereIn('circle_id', $this->circleId ? [$this->circleId] : $this->circles->pluck('id'))
            ->where(fn ($q) => $q->whereNull('acted_at')->orWhere('acted_at', '>=', now()->subDays(7)))
            ->with(['student', 'circle', 'actor'])
            ->orderByRaw('acted_at is not null')
            ->latest('week_ends_on')
            ->limit(100)
            ->get();
    }

    public function act(int $alertId): void
    {
        abort_unless($this->role === 'teacher', 403);

        $alert = CommitmentAlert::findOrFail($alertId);

        abort_unless(Scope::forRole('teacher')->reachableCircleIds()->contains($alert->circle_id), 403);

        $this->validate([
            "acts.{$alertId}.action" => ['required', 'in:'.implode(',', array_keys(CommitmentAlert::ACTIONS))],
            "acts.{$alertId}.note" => ['nullable', 'string', 'max:500'],
        ], [
            "acts.{$alertId}.action.required" => __('اختر ما فعلته.'),
        ]);

        $alert->update([
            'action' => $this->acts[$alertId]['action'],
            'note' => ($this->acts[$alertId]['note'] ?? '') ?: null,
            'acted_at' => now(),
            'acted_by' => Auth::guard('teacher')->id(),
        ]);

        unset($this->acts[$alertId], $this->alerts);

        Flux::toast(__('سُجّل الإجراء.'), variant: 'success');
    }
};
?>

<div class="space-y-5" dir="rtl">
    <div class="flex flex-wrap items-end gap-3">
        <flux:field>
            <flux:label>{{ __('الفترة') }}</flux:label>
            <flux:select wire:model.live="period">
                @foreach (CommitmentService::PERIODS as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        @if ($period === 'custom')
            <flux:field>
                <flux:label>{{ __('من') }}</flux:label>
                <flux:input type="date" wire:model.live="from" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('إلى') }}</flux:label>
                <flux:input type="date" wire:model.live="to" />
            </flux:field>
        @endif

        @if ($this->stages->count() > 1)
            <flux:field>
                <flux:label>{{ __('البرنامج') }}</flux:label>
                <flux:select wire:model.live="stageId">
                    <flux:select.option value="">{{ __('كل البرامج') }}</flux:select.option>
                    @foreach ($this->stages as $stage)
                        <flux:select.option value="{{ $stage->id }}">{{ $stage->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        @endif

        @if ($this->circles->count() > 1)
            <flux:field>
                <flux:label>{{ __('المجموعة') }}</flux:label>
                <flux:select wire:model.live="circleId">
                    <flux:select.option value="">{{ __('كل المجموعات') }}</flux:select.option>
                    @foreach ($this->circles as $circle)
                        <flux:select.option value="{{ $circle->id }}">{{ $circle->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        @endif

        <p class="text-xs text-zinc-500 pb-2.5">
            {{ $this->range[0]->format('Y/m/d') }} — {{ $this->range[1]->format('Y/m/d') }}
        </p>
    </div>

    {{-- The students below the line last week, and what their teachers did. --}}
    @if ($this->alerts->isNotEmpty())
        <flux:card class="space-y-3" data-alerts>
            <div>
                <flux:heading size="lg">{{ __('يحتاج إجراء') }}</flux:heading>
                <flux:subheading>
                    {{ __('طلاب كان التزامهم تحت :n% في أسبوع. إن مضى يومان دون إجراء من المعلم يُرفع الأمر للمشرف.', ['n' => (int) CommitmentService::THRESHOLD]) }}
                </flux:subheading>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->alerts as $alert)
                    <div wire:key="alert-{{ $alert->id }}" class="py-3 space-y-2" data-alert="{{ $alert->id }}">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-bold text-zinc-900 dark:text-white">{{ $alert->student?->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $alert->circle?->name }}</span>
                            <span class="text-xs text-zinc-500">{{ __('أسبوع :from — :to', ['from' => $alert->week_starts_on->format('m/d'), 'to' => $alert->week_ends_on->format('m/d')]) }}</span>
                            @if ($alert->plan_percent !== null)
                                <flux:badge size="sm" :color="$alert->plan_percent < CommitmentService::THRESHOLD ? 'red' : 'zinc'">{{ __('الخطة :p%', ['p' => \App\Support\SelfProgramUnit::number($alert->plan_percent)]) }}</flux:badge>
                            @endif
                            @if ($alert->self_percent !== null)
                                <flux:badge size="sm" :color="$alert->self_percent < CommitmentService::THRESHOLD ? 'red' : 'zinc'">{{ __('البرنامج الذاتي :p%', ['p' => \App\Support\SelfProgramUnit::number($alert->self_percent)]) }}</flux:badge>
                            @endif

                            @if ($alert->acted_at)
                                <flux:badge size="sm" color="emerald" icon="check">{{ $alert->actionLabel() }}</flux:badge>
                            @elseif ($alert->escalated_at)
                                <flux:badge size="sm" color="red">{{ __('رُفع للمشرف') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('بانتظار المعلم') }}</flux:badge>
                            @endif
                        </div>

                        @if ($alert->acted_at)
                            <p class="text-xs text-zinc-500">
                                {{ $alert->actor?->name }} · {{ $alert->acted_at->diffForHumans() }}@if ($alert->note) · {{ $alert->note }}@endif
                            </p>
                        @elseif ($role === 'teacher')
                            <div class="flex flex-wrap items-start gap-2">
                                <div class="w-full sm:w-56">
                                    <flux:select wire:model="acts.{{ $alert->id }}.action" placeholder="{{ __('ماذا فعلت؟') }}">
                                        @foreach (CommitmentAlert::ACTIONS as $key => $label)
                                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="acts.{{ $alert->id }}.action" />
                                </div>
                                <div class="w-full sm:flex-1 sm:min-w-0">
                                    <flux:input wire:model="acts.{{ $alert->id }}.note" placeholder="{{ __('ملاحظة (اختياري)') }}" />
                                </div>
                                <flux:button size="sm" variant="primary" icon="check" class="mt-1" wire:click="act({{ $alert->id }})">
                                    {{ __('سجّل') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    {{-- The groups, each with its two bars. --}}
    @if (! $circleId && $this->groups->count() > 1)
        <flux:card class="p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('المجموعة') }}</flux:table.column>
                        <flux:table.column>{{ __('الطلاب') }}</flux:table.column>
                        <flux:table.column>{{ __('الخطة القرآنية') }}</flux:table.column>
                        <flux:table.column>{{ __('البرنامج الذاتي') }}</flux:table.column>
                        <flux:table.column>{{ __('المتأخرات') }}</flux:table.column>
                        <flux:table.column>{{ __('تحت :n%', ['n' => (int) CommitmentService::THRESHOLD]) }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->groups as $group)
                            <flux:table.row wire:key="group-{{ $group['circle']->id }}" data-group="{{ $group['circle']->id }}">
                                <flux:table.cell>
                                    <button type="button" class="font-medium text-start hover:underline" wire:click="$set('circleId', {{ $group['circle']->id }})">
                                        {{ $group['circle']->name }}
                                    </button>
                                </flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $group['students'] }}</flux:table.cell>
                                <flux:table.cell><x-commitment-bar :percent="$group['plan']" /></flux:table.cell>
                                <flux:table.cell><x-commitment-bar :percent="$group['self']" /></flux:table.cell>
                                <flux:table.cell class="text-xs whitespace-nowrap">
                                    {{ __('خطة: :p · ذاتي: :s', ['p' => $group['plan_owed'], 's' => $group['self_owed']]) }}
                                </flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $group['behind'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    @endif

    {{-- The students of the chosen group, or of all of them when there is one. --}}
    @if ($circleId || $this->groups->count() <= 1)
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-48"><span class="text-xs text-zinc-500">{{ __('الخطة القرآنية') }}</span><x-commitment-bar :percent="$this->overall['plan']" /></div>
            <div class="flex-1 min-w-48"><span class="text-xs text-zinc-500">{{ __('البرنامج الذاتي') }}</span><x-commitment-bar :percent="$this->overall['self']" /></div>
        </div>

        <flux:card class="p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('الطالب') }}</flux:table.column>
                        <flux:table.column>{{ __('الخطة القرآنية') }}</flux:table.column>
                        <flux:table.column>{{ __('البرنامج الذاتي') }}</flux:table.column>
                        <flux:table.column>{{ __('المتأخرات') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->measured['students'] as $student)
                            @php
                                $row = $this->measured['rows'][$student->id];
                            @endphp
                            <flux:table.row wire:key="student-{{ $student->id }}" data-student="{{ $student->id }}">
                                <flux:table.cell class="font-medium whitespace-nowrap">
                                    {{ $student->name }}
                                    @if (CommitmentService::isBehind($row))
                                        <flux:badge size="sm" color="red" class="ms-1">{{ __('متأخر') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-commitment-bar :percent="$row['plan']['percent'] ?? null"
                                        :detail="$row['plan'] ? __(':d من :t', ['d' => $row['plan']['done'], 't' => $row['plan']['due']]) : null" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-commitment-bar :percent="$row['self']['percent'] ?? null"
                                        :detail="$row['self'] ? __('أسابيع: :n', ['n' => $row['self']['weeks']]) : null" />
                                </flux:table.cell>
                                <flux:table.cell class="text-xs whitespace-nowrap">
                                    @if (($row['plan']['owed'] ?? 0) > 0)
                                        <div>{{ __('الخطة: :n لم تُسمَّع', ['n' => $row['plan']['owed']]) }}</div>
                                    @endif
                                    @if (($row['self']['owed'] ?? 0) > 0)
                                        <div>{{ __('الذاتي: :n مهام لم تكتمل', ['n' => $row['self']['owed']]) }}</div>
                                    @endif
                                    @if (($row['plan']['owed'] ?? 0) === 0 && ($row['self']['owed'] ?? 0) === 0)
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">{{ __('لا طلاب مشاركون هنا.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    @endif
</div>
