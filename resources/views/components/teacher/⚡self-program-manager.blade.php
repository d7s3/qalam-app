<?php

use App\Models\Circle;
use App\Support\Scope;
use App\Models\SelfProgramDayOverride;
use App\Models\SelfProgramWeek;
use App\Models\Student;
use App\Services\SelfProgramService;
use App\Models\SelfProgramTrack;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $circleId = null;

    public string $tab = 'enrichment';

    /** Circle settings, mirrored so the toggles have somewhere to bind. */
    public bool $isQuranic = true;

    public bool $unlockOnCompletion = false;

    /** The open enrichment week and its five editable tracks. */
    public ?int $weekId = null;

    public array $rows = [];

    public string $newStartsOn = '';

    /** The daily-split override being written. */
    public ?int $overrideItemId = null;

    public ?int $overrideStudentId = null;

    public string $overrideDay = '';

    public ?float $overrideAmount = null;

    public function mount(): void
    {
        $this->circleId = $this->circles->first()?->id;
        $this->newStartsOn = Carbon::today()->startOfWeek(Carbon::SUNDAY)->toDateString();
        $this->overrideDay = Carbon::today()->toDateString();
        $this->syncCircle();
    }

    /**
     * The circles this teacher runs.
     *
     * @return Collection<int, Circle>
     */
    #[Computed]
    public function circles(): Collection
    {
        // Read as a reach rather than as a teacher's own cohorts: a supervisor
        // who holds this screen through his office teaches none, and would
        // otherwise open it to an empty list.
        return Scope::forRoute()->applyToCircles(Circle::query())->orderBy('name')->get();
    }

    #[Computed]
    public function circle(): ?Circle
    {
        return $this->circleId ? $this->circles->firstWhere('id', $this->circleId) : null;
    }

    /** @return Collection<int, SelfProgramWeek> */
    #[Computed]
    public function weeks(): Collection
    {
        return $this->circleId
            ? SelfProgramWeek::enrichment()->where('circle_id', $this->circleId)->orderBy('week_number')->get()
            : collect();
    }

    #[Computed]
    public function week(): ?SelfProgramWeek
    {
        return $this->weekId ? SelfProgramWeek::with('items')->find($this->weekId) : null;
    }

    /**
     * The students of the circle, with how far through their own week each is.
     *
     * Read in one pass by the service rather than a student at a time: asked
     * separately this was three queries each, and the same week was fetched
     * once per student.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function progress(): Collection
    {
        $circle = $this->circle;

        if (! $circle) {
            return collect();
        }

        $students = Student::where('circle_id', $circle->id)->orderBy('name')->get();

        return collect(app(SelfProgramService::class)->progressForStudents($students))->values();
    }

    /** The self-programme week the overrides are written against. */
    #[Computed]
    public function selfWeek(): ?SelfProgramWeek
    {
        $stageId = $this->circle?->stage_id;

        if (! $stageId) {
            return null;
        }

        $today = Carbon::today()->toDateString();

        return SelfProgramWeek::self()
            ->where('stage_id', $stageId)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->with('items')
            ->first();
    }

    public function updatedCircleId(): void
    {
        unset($this->circle, $this->weeks, $this->week, $this->progress, $this->selfWeek);
        $this->syncCircle();
    }

    private function syncCircle(): void
    {
        $circle = $this->circle;

        $this->isQuranic = (bool) ($circle?->is_quranic ?? true);
        $this->unlockOnCompletion = (bool) ($circle?->self_program_unlock_on_completion ?? false);
        $this->weekId = $this->weeks->last()?->id;
        $this->overrideItemId = $this->selfWeek?->items->first()?->id;
        $this->loadRows();
    }

    private function guardCircle(): Circle
    {
        $circle = $this->circle;

        abort_unless($circle instanceof Circle, 403);

        return $circle;
    }

    public function saveSettings(): void
    {
        $circle = $this->guardCircle();

        $circle->update([
            'is_quranic' => $this->isQuranic,
            'self_program_unlock_on_completion' => $this->unlockOnCompletion,
        ]);

        unset($this->circles, $this->circle);

        Flux::toast(text: 'حُفظت إعدادات الدفعة.', variant: 'success');
    }

    public function openWeek(int $id): void
    {
        $week = SelfProgramWeek::findOrFail($id);

        abort_unless($week->circle_id === $this->circleId && $this->circle, 403);

        $this->weekId = $id;
        unset($this->week);
        $this->loadRows();
    }

    private function loadRows(): void
    {
        $this->rows = [];
        $week = $this->week;

        if (! $week) {
            return;
        }

        $week->ensureAllTracks();
        $week->load('items');
        unset($this->week);

        foreach (SelfProgramTrack::ordered() as $track) {
            $item = $week->items->firstWhere('track.key', $track->key);

            $this->rows[$track->value] = [
                'description' => $item?->description ?? '',
                'target_amount' => $item ? (float) $item->target_amount : 0,
                'unit' => $track->fixedUnit() ?? ($item?->unit ?: $track->defaultUnit()),
            ];
        }
    }

    public function addWeek(): void
    {
        $circle = $this->guardCircle();

        $this->validate(['newStartsOn' => ['required', 'date']], [], ['newStartsOn' => 'تاريخ البداية']);

        $starts = Carbon::parse($this->newStartsOn)->startOfDay();

        $week = SelfProgramWeek::create([
            'circle_id' => $circle->id,
            'program_type' => SelfProgramWeek::TYPE_ENRICHMENT,
            'week_number' => ($this->weeks->max('week_number') ?? 0) + 1,
            'starts_on' => $starts,
            'ends_on' => $starts->copy()->addDays(6),
            'created_by_id' => Auth::guard('teacher')->id(),
            'created_by_type' => Auth::guard('teacher')->user()::class,
        ]);

        $week->ensureAllTracks();

        unset($this->weeks);
        $this->weekId = $week->id;
        $this->newStartsOn = $starts->copy()->addDays(7)->toDateString();
        $this->loadRows();

        Flux::toast(text: 'أُضيف أسبوع إثرائي.', variant: 'success');
    }

    public function saveWeek(): void
    {
        $week = $this->week;

        abort_unless($week && $week->circle_id === $this->circleId && $this->circle, 403);

        $this->validate([
            'rows.*.description' => ['nullable', 'string', 'max:500'],
            'rows.*.target_amount' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'rows.*.unit' => ['nullable', 'string', 'max:30'],
        ]);

        foreach (SelfProgramTrack::ordered() as $track) {
            $row = $this->rows[$track->value] ?? null;

            if (! $row) {
                continue;
            }

            $week->items()->updateOrCreate(['track' => $track->value], [
                'description' => $row['description'] ?: null,
                'target_amount' => (float) ($row['target_amount'] ?: 0),
                'unit' => $track->fixedUnit() ?? ($row['unit'] ?: $track->defaultUnit()),
            ]);
        }

        unset($this->week);

        Flux::toast(text: 'حُفظ الأسبوع الإثرائي.', variant: 'success');
    }

    /**
     * Overrule the suggested share for one day — for the whole circle, or for a
     * single student whose circumstances differ.
     */
    public function saveOverride(): void
    {
        $circle = $this->guardCircle();

        $this->validate([
            'overrideItemId' => ['required'],
            'overrideDay' => ['required', 'date'],
            'overrideAmount' => ['required', 'numeric', 'min:0', 'max:9999'],
        ], [], [
            'overrideItemId' => 'المجال',
            'overrideDay' => 'اليوم',
            'overrideAmount' => 'المقدار',
        ]);

        $item = $this->selfWeek?->items->firstWhere('id', $this->overrideItemId);

        abort_unless($item !== null, 404);

        // A student named here must be one of this circle's own.
        if ($this->overrideStudentId) {
            abort_unless(
                Student::where('id', $this->overrideStudentId)->where('circle_id', $circle->id)->exists(),
                403,
            );
        }

        SelfProgramDayOverride::updateOrCreate(
            [
                'self_program_item_id' => $item->id,
                'circle_id' => $this->overrideStudentId ? null : $circle->id,
                'student_id' => $this->overrideStudentId ?: null,
                'day_date' => Carbon::parse($this->overrideDay)->startOfDay(),
            ],
            ['amount' => $this->overrideAmount],
        );

        unset($this->overrides);
        $this->overrideAmount = null;

        Flux::toast(text: 'حُفظ التوزيع.', variant: 'success');
    }

    /** @return Collection<int, SelfProgramDayOverride> */
    #[Computed]
    public function overrides(): Collection
    {
        $week = $this->selfWeek;

        if (! $week || ! $this->circleId) {
            return collect();
        }

        return SelfProgramDayOverride::with(['item', 'student'])
            ->whereIn('self_program_item_id', $week->items->pluck('id'))
            ->where(fn ($q) => $q->where('circle_id', $this->circleId)
                ->orWhereIn('student_id', Student::where('circle_id', $this->circleId)->pluck('id')))
            ->orderBy('day_date')
            ->get();
    }

    public function removeOverride(int $id): void
    {
        $circle = $this->guardCircle();

        $override = SelfProgramDayOverride::findOrFail($id);

        $belongs = $override->circle_id === $circle->id
            || ($override->student_id && Student::where('id', $override->student_id)->where('circle_id', $circle->id)->exists());

        abort_unless($belongs, 403);

        $override->delete();
        unset($this->overrides);
    }

    public function with(): array
    {
        return ['tracks' => SelfProgramTrack::ordered()];
    }
};
?>

<div class="space-y-6" dir="rtl">
    @if ($this->circles->isEmpty())
        <flux:card class="text-center py-12">
            <flux:icon icon="exclamation-triangle" class="size-10 mx-auto text-amber-400" />
            <flux:heading size="lg" class="mt-3">{{ __('لا توجد دفعات مسندة إليك') }}</flux:heading>
        </flux:card>
    @else
        <flux:field>
            <flux:label>{{ __('الدفعة') }}</flux:label>
            <flux:select wire:model.live="circleId">
                @foreach ($this->circles as $circle)
                    <flux:select.option value="{{ $circle->id }}">{{ $circle->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        {{-- إعدادات الدفعة --}}
        <flux:card>
            <flux:heading size="lg">{{ __('إعدادات الدفعة') }}</flux:heading>
            <div class="mt-4 space-y-4">
                <flux:switch wire:model="isQuranic"
                    label="{{ __('حلقة قرآنية') }}"
                    description="{{ __('يفتح الحفظ والمراجعة، ويجعل تسجيلك للتسميع يكتب ورد الطالب تلقائياً.') }}" />
                <flux:switch wire:model="unlockOnCompletion"
                    label="{{ __('إنهاء الأسبوع يفتح التالي') }}"
                    description="{{ __('من أنهى محتوى أسبوعه كاملاً فُتح له الأسبوع التالي قبل موعده.') }}" />
                <flux:button variant="primary" wire:click="saveSettings" icon="check">{{ __('حفظ') }}</flux:button>
            </div>
        </flux:card>

        <div class="flex flex-wrap gap-2 border-b border-zinc-100 dark:border-zinc-800 pb-3">
            <flux:button size="sm" :variant="$tab === 'enrichment' ? 'primary' : 'ghost'" wire:click="$set('tab', 'enrichment')">
                {{ __('البرنامج الإثرائي') }}
            </flux:button>
            <flux:button size="sm" :variant="$tab === 'split' ? 'primary' : 'ghost'" wire:click="$set('tab', 'split')">
                {{ __('التوزيع اليومي') }}
            </flux:button>
            <flux:button size="sm" :variant="$tab === 'progress' ? 'primary' : 'ghost'" wire:click="$set('tab', 'progress')">
                {{ __('تقدّم الطلاب') }}
            </flux:button>
        </div>

        {{-- ============ البرنامج الإثرائي ============ --}}
        @if ($tab === 'enrichment')
        <div class="space-y-5">
            <div class="flex items-end gap-2 max-w-md">
                <flux:field class="flex-1">
                    <flux:label>{{ __('بداية أسبوع إثرائي جديد') }}</flux:label>
                    <flux:input type="date" wire:model="newStartsOn" />
                    <flux:error name="newStartsOn" />
                </flux:field>
                <flux:button variant="primary" wire:click="addWeek" class="mb-0.5" icon="plus">
                    {{ __('أضف') }}
                </flux:button>
            </div>

            @if ($this->weeks->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->weeks as $item)
                        <flux:button wire:key="ew-{{ $item->id }}" size="sm"
                            :variant="$item->id === $weekId ? 'primary' : 'filled'"
                            wire:click="openWeek({{ $item->id }})">
                            {{ __('الأسبوع') }} {{ $item->week_number }}
                        </flux:button>
                    @endforeach
                </div>
            @endif

            @if ($this->week)
                <flux:card>
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                        <flux:heading size="lg">
                            {{ __('الأسبوع الإثرائي') }} {{ $this->week->week_number }}
                        </flux:heading>
                        <flux:button variant="primary" wire:click="saveWeek" icon="check">{{ __('حفظ') }}</flux:button>
                    </div>

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($tracks as $track)
                            <div wire:key="er-{{ $track->value }}" class="py-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                                <div class="md:col-span-3 flex items-center gap-2.5 pt-1">
                                    <flux:icon :icon="$track->icon()" class="size-5 text-zinc-500" />
                                    <span class="font-bold text-zinc-900 dark:text-white">{{ $track->label() }}</span>
                                </div>
                                <div class="md:col-span-5">
                                    <flux:input wire:model="rows.{{ $track->value }}.description"
                                        placeholder="{{ __('المحتوى الإثرائي') }}" />
                                </div>
                                <div class="md:col-span-2">
                                    <flux:input type="number" step="0.25" min="0"
                                        wire:model="rows.{{ $track->value }}.target_amount"
                                        placeholder="{{ __('المقدار') }}" />
                                </div>
                                <div class="md:col-span-2">
                                    @if ($track->fixedUnit())
                                        <flux:input value="{{ $track->fixedUnit() }}" disabled />
                                    @else
                                        <flux:input wire:model="rows.{{ $track->value }}.unit"
                                            placeholder="{{ __('الوحدة') }}" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-xs text-zinc-500 dark:text-zinc-400 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        {{ __('يظهر هذا المحتوى لمن بلغ ٥٠٪ من متوسط برنامجه الذاتي هذا الأسبوع فقط.') }}
                    </p>
                </flux:card>
            @endif
        </div>

        {{-- ============ التوزيع اليومي ============ --}}
        @endif

        @if ($tab === 'split')
        <div class="space-y-5">
            @if (! $this->selfWeek)
                <flux:card class="text-center py-10">
                    <flux:subheading>{{ __('لا يوجد أسبوع ذاتي جارٍ لبرنامج هذه الدفعة.') }}</flux:subheading>
                </flux:card>
            @else
                <flux:card>
                    <flux:heading size="lg">{{ __('تعديل نصيب يوم') }}</flux:heading>
                    <flux:subheading class="mt-1">
                        {{ __('يحلّ محلّ المقترح التلقائي. وتعديل الطالب يتقدّم على تعديل الدفعة.') }}
                    </flux:subheading>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-start">
                        <flux:field>
                            <flux:label>{{ __('المجال') }}</flux:label>
                            <flux:select wire:model="overrideItemId">
                                @foreach ($this->selfWeek->items as $item)
                                    <flux:select.option value="{{ $item->id }}">{{ $item->track->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="overrideItemId" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('لمن') }}</flux:label>
                            <flux:select wire:model="overrideStudentId">
                                <flux:select.option value="">{{ __('الدفعة كلها') }}</flux:select.option>
                                @foreach ($this->progress as $row)
                                    <flux:select.option value="{{ $row['student']->id }}">{{ $row['student']->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('اليوم') }}</flux:label>
                            <flux:input type="date" wire:model="overrideDay" />
                            <flux:error name="overrideDay" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('المقدار') }}</flux:label>
                            <div class="flex items-end gap-2">
                                <flux:input type="number" step="0.25" min="0" wire:model="overrideAmount" />
                                <flux:button variant="primary" wire:click="saveOverride">{{ __('حفظ') }}</flux:button>
                            </div>
                            <flux:error name="overrideAmount" />
                        </flux:field>
                    </div>
                </flux:card>

                @if ($this->overrides->isNotEmpty())
                    <flux:card class="p-0 overflow-hidden">
                        <div class="overflow-x-auto">
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>{{ __('المجال') }}</flux:table.column>
                                    <flux:table.column>{{ __('لمن') }}</flux:table.column>
                                    <flux:table.column>{{ __('اليوم') }}</flux:table.column>
                                    <flux:table.column>{{ __('المقدار') }}</flux:table.column>
                                    <flux:table.column />
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($this->overrides as $override)
                                        <flux:table.row wire:key="ov-{{ $override->id }}">
                                            <flux:table.cell>{{ $override->item?->track->label() }}</flux:table.cell>
                                            <flux:table.cell>{{ $override->student?->name ?? __('الدفعة كلها') }}</flux:table.cell>
                                            <flux:table.cell class="tabular-nums">{{ $override->day_date->toDateString() }}</flux:table.cell>
                                            <flux:table.cell class="tabular-nums">{{ rtrim(rtrim(number_format($override->amount, 2), '0'), '.') }}</flux:table.cell>
                                            <flux:table.cell>
                                                <flux:button size="sm" variant="subtle" icon="trash"
                                                    wire:click="removeOverride({{ $override->id }})" />
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    </flux:card>
                @endif
            @endif
        </div>

        {{-- ============ تقدّم الطلاب ============ --}}
        @endif

        @if ($tab === 'progress')
        <div>
            <flux:card class="p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('الطالب') }}</flux:table.column>
                            <flux:table.column>{{ __('تقدّم الأسبوع') }}</flux:table.column>
                            <flux:table.column>{{ __('الإثرائي') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($this->progress as $row)
                                <flux:table.row wire:key="pr-{{ $row['student']->id }}">
                                    <flux:table.cell class="font-medium">{{ $row['student']->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($row['overall'] === null)
                                            <span class="text-xs text-zinc-400">{{ __('لا أسبوع جارٍ') }}</span>
                                        @else
                                            <div class="flex items-center gap-2 min-w-40">
                                                <div class="flex-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $row['overall'] }}%"></div>
                                                </div>
                                                <span class="text-xs tabular-nums w-10">{{ $row['overall'] }}%</span>
                                            </div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$row['unlocked'] ? 'lime' : 'zinc'" size="sm">
                                            {{ $row['unlocked'] ? __('مفتوح') : __('مقفل') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="3" class="text-center text-zinc-400">
                                        {{ __('لا طلاب في هذه الدفعة.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </div>
        @endif
    @endif
</div>
