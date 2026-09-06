<?php

use App\Models\Circle;
use App\Models\PeriodValue;
use App\Models\Stage;
use App\Support\Scope;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Writing the character the circle works on, and for how long.
 *
 * A term is laid out the way the self programme's year is: one at a time when
 * that is what is wanted, or a run of weeks generated at once and then edited —
 * because nobody writes thirty of these by hand, and a value nobody wrote is a
 * value nobody works on.
 */
new class extends Component
{
    public string $asRole = 'supervisor';

    public string $title = '';

    public string $practice = '';

    public string $evidence = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public ?int $stageId = null;

    public ?int $circleId = null;

    public ?int $editingId = null;

    /** For laying out a run of weeks in one go. */
    public string $generateFrom = '';

    public int $generateWeeks = 4;

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
        $this->startsOn = now('Asia/Riyadh')->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
        $this->endsOn = now('Asia/Riyadh')->startOfWeek(Carbon::SUNDAY)->addDays(6)->format('Y-m-d');
        $this->generateFrom = $this->startsOn;
        $this->stageId = $this->scope()->stageIds()?->first();
    }

    private function scope(): Scope
    {
        return Scope::forRole($this->asRole);
    }

    /** Refuse a programme this office does not hold. */
    private function guardStage(?int $stageId): void
    {
        $reach = $this->scope()->stageIds();

        abort_if($stageId !== null && $reach !== null && ! $reach->contains($stageId), 403);
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'practice' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'string', 'max:2000'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
        ], [], [
            'title' => __('القيمة'),
            'startsOn' => __('من'),
            'endsOn' => __('إلى'),
        ]);

        $this->guardStage($this->stageId);

        PeriodValue::updateOrCreate(
            ['id' => $this->editingId],
            [
                'title' => $this->title,
                'practice' => $this->practice ?: null,
                'evidence' => $this->evidence ?: null,
                'starts_on' => $this->startsOn,
                'ends_on' => $this->endsOn,
                'stage_id' => $this->stageId,
                'circle_id' => $this->circleId,
                'created_by_id' => $this->scope()->user()?->id,
            ],
        );

        $this->resetForm();

        Flux::toast(__('حُفظت القيمة'), variant: 'success');
    }

    public function edit(int $id): void
    {
        $value = $this->reachable()->firstWhere('id', $id) ?? abort(404);

        $this->editingId = $value->id;
        $this->title = $value->title;
        $this->practice = $value->practice ?? '';
        $this->evidence = $value->evidence ?? '';
        $this->startsOn = $value->starts_on->format('Y-m-d');
        $this->endsOn = $value->ends_on->format('Y-m-d');
        $this->stageId = $value->stage_id;
        $this->circleId = $value->circle_id;
    }

    public function remove(int $id): void
    {
        ($this->reachable()->firstWhere('id', $id) ?? abort(404))->delete();

        Flux::toast(__('حُذفت'), variant: 'success');
    }

    /**
     * Lay out a run of empty weeks to be filled in.
     *
     * Each is a week long and unnamed but for its number, so the supervisor
     * edits rather than creates — the same trade the self programme's year
     * generation makes.
     */
    public function generate(): void
    {
        $this->guardStage($this->stageId);

        $weeks = max(1, min(52, $this->generateWeeks));
        $start = Carbon::parse($this->generateFrom)->startOfDay();

        for ($i = 0; $i < $weeks; $i++) {
            $from = $start->copy()->addWeeks($i);

            PeriodValue::create([
                'title' => __('قيمة الأسبوع :n', ['n' => $i + 1]),
                'starts_on' => $from->format('Y-m-d'),
                'ends_on' => $from->copy()->addDays(6)->format('Y-m-d'),
                'stage_id' => $this->stageId,
                'created_by_id' => $this->scope()->user()?->id,
            ]);
        }

        Flux::toast(__(':n أسابيع بانتظار ما تكتبه فيها', ['n' => $weeks]), variant: 'success');
    }

    private function resetForm(): void
    {
        $this->reset(['title', 'practice', 'evidence', 'editingId', 'circleId']);
    }

    /** @return \Illuminate\Support\Collection<int, PeriodValue> */
    private function reachable()
    {
        $reach = $this->scope()->stageIds();

        return PeriodValue::query()
            ->with(['stage', 'circle'])
            ->when($reach !== null, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('stage_id')
                ->orWhereIn('stage_id', $reach ?? [])))
            ->orderByDesc('starts_on')
            ->take(80)
            ->get();
    }

    public function with(): array
    {
        $reach = $this->scope()->stageIds();

        return [
            'values' => $this->reachable(),
            'stages' => $reach === null
                ? Stage::orderBy('name')->get()
                : Stage::whereIn('id', $reach)->orderBy('name')->get(),
            'circles' => $this->stageId
                ? Circle::where('stage_id', $this->stageId)->orderBy('name')->get()
                : collect(),
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ __('قيم الفترة') }}</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('الخُلق الذي تعمل عليه الدفعة، ومعه العمل به. تظهر لكل من يعنيه في يومه.') }}
        </flux:subheading>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="sm">{{ $editingId ? __('تعديل قيمة') : __('قيمة جديدة') }}</flux:heading>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('القيمة') }}</flux:label>
                <flux:input wire:model="title" placeholder="{{ __('مثال: الصدق') }}" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('البرنامج') }}</flux:label>
                <flux:select wire:model.live="stageId" placeholder="{{ __('كل المركز') }}">
                    <flux:select.option value="">{{ __('كل المركز') }}</flux:select.option>
                    @foreach($stages as $stage)
                        <flux:select.option value="{{ $stage->id }}">{{ $stage->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('من') }}</flux:label>
                <flux:input type="date" wire:model="startsOn" />
                <flux:error name="startsOn" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('إلى') }}</flux:label>
                <flux:input type="date" wire:model="endsOn" />
                <flux:error name="endsOn" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('العمل بها') }}</flux:label>
                <flux:textarea wire:model="practice" rows="2"
                    placeholder="{{ __('مثال: لا يمرّ يومٌ بكذبة، ولو مازحاً.') }}" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('شاهدها') }}</flux:label>
                <flux:textarea wire:model="evidence" rows="2"
                    placeholder="{{ __('آية أو حديث أو أثر') }}" />
            </flux:field>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="primary" class="!bg-maroon hover:!bg-burgundy" wire:click="save">
                {{ $editingId ? __('حفظ') : __('إضافة') }}
            </flux:button>
            @if($editingId)
                <flux:button variant="ghost" wire:click="$set('editingId', null)">{{ __('إلغاء') }}</flux:button>
            @endif
        </div>
    </flux:card>

    <flux:card class="flex items-end gap-3 flex-wrap">
        <flux:field>
            <flux:label>{{ __('توليد أسابيع من') }}</flux:label>
            <flux:input type="date" wire:model="generateFrom" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('عددها') }}</flux:label>
            <flux:input type="number" min="1" max="52" wire:model="generateWeeks" class="w-24" />
        </flux:field>
        <flux:button variant="ghost" wire:click="generate">{{ __('ولّد') }}</flux:button>
        <flux:text class="text-xs text-zinc-400">{{ __('تُنشأ فارغة بأسمائها، ثم تكتب فيها.') }}</flux:text>
    </flux:card>

    <div class="space-y-2">
        @forelse($values as $value)
            <flux:card wire:key="value-{{ $value->id }}" class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $value->title }}</div>
                    @if($value->practice)
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ $value->practice }}</div>
                    @endif
                    <div class="text-xs text-zinc-400 mt-1 flex items-center gap-2 flex-wrap">
                        <x-hijri-date :date="$value->starts_on" />
                        <span>—</span>
                        <x-hijri-date :date="$value->ends_on" />
                        <span>· {{ $value->stage?->name ?? __('كل المركز') }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $value->id }})" />
                    <flux:button size="sm" variant="ghost" icon="trash" class="text-rose-500"
                        wire:confirm="{{ __('حذف هذه القيمة؟') }}" wire:click="remove({{ $value->id }})" />
                </div>
            </flux:card>
        @empty
            <flux:card><flux:text class="text-zinc-400">{{ __('لا قيم بعد.') }}</flux:text></flux:card>
        @endforelse
    </div>
</div>
