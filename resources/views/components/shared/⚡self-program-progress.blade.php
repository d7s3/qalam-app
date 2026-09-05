<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Services\SelfProgramService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * How far a set of students is through the current week, read-only.
 *
 * The same table answers for a supervisor over his stages, a manager over the
 * whole academy and a guardian over his own children — only the set of students
 * differs, and that follows from who is asking.
 */
new class extends Component
{
    /** Which guard is looking; decides whose students are shown. */
    public string $role = 'supervisor';

    public ?int $stageId = null;

    public string $search = '';

    public function mount(): void
    {
        $this->stageId = $this->stages->first()?->id;
    }

    /** The stages the viewer may look at. Guardians have none — they see children. */
    /** @return Collection<int, Stage> */
    #[Computed]
    public function stages(): Collection
    {
        return match ($this->role) {
            'supervisor' => Auth::guard('supervisor')->user()->stages()->orderBy('name')->get(),
            'manager' => Stage::orderBy('name')->get(),
            default => collect(),
        };
    }

    /**
     * How many students one screen will show before it stops.
     */
    private const LIMIT = 200;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): Collection
    {
        return collect(app(SelfProgramService::class)->progressForStudents($this->students()))->values();
    }

    /**
     * Whether the list ran up against its limit, so the view can say so rather
     * than presenting a truncated stage as a complete one.
     */
    #[Computed]
    public function truncated(): bool
    {
        return $this->students()->count() >= self::LIMIT;
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(): Collection
    {
        $query = Student::query()->with('circle')->orderBy('name');

        if ($this->role === 'guardian') {
            $query->where('guardian_id', Auth::guard('guardian')->id());
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

        return $query->limit(self::LIMIT)->get();
    }
};
?>

<div class="space-y-5" dir="rtl">
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

        @if ($role !== 'guardian')
            <flux:field class="flex-1 min-w-48">
                <flux:label>{{ __('بحث') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="search" placeholder="{{ __('اسم الطالب') }}" icon="magnifying-glass" />
            </flux:field>
        @endif
    </div>

    @if ($this->truncated)
        <div class="flex items-start gap-2 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 px-3 py-2.5">
            <flux:icon icon="information-circle" class="size-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
                {{ __('تُعرض أول ٢٠٠ طالب فقط. ابحث باسم الطالب للوصول لمن ليس في القائمة.') }}
            </p>
        </div>
    @endif

    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('الطالب') }}</flux:table.column>
                    <flux:table.column>{{ __('الحلقة') }}</flux:table.column>
                    <flux:table.column>{{ __('تقدّم الأسبوع') }}</flux:table.column>
                    <flux:table.column>{{ __('المجالات') }}</flux:table.column>
                    <flux:table.column>{{ __('متأخرات') }}</flux:table.column>
                    <flux:table.column>{{ __('الإثرائي') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->rows as $row)
                        <flux:table.row wire:key="sp-{{ $row['student']->id }}">
                            <flux:table.cell class="font-medium whitespace-nowrap">{{ $row['student']->name }}</flux:table.cell>
                            <flux:table.cell class="text-zinc-500 whitespace-nowrap">
                                {{ $row['student']->circle?->name ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($row['overall'] === null)
                                    <span class="text-xs text-zinc-400">{{ __('لا أسبوع جارٍ') }}</span>
                                @else
                                    <div class="flex items-center gap-2 min-w-36">
                                        <div class="flex-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                            <div class="h-full rounded-full {{ $row['overall'] >= 50 ? 'bg-emerald-500' : 'bg-amber-500' }}"
                                                style="width: {{ max($row['overall'], 1) }}%"></div>
                                        </div>
                                        <span class="text-xs tabular-nums w-10">{{ $row['overall'] }}%</span>
                                    </div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-1">
                                    @foreach ($row['tracks'] as $track)
                                        <span wire:key="t-{{ $row['student']->id }}-{{ $track['item']->id }}"
                                            title="{{ $track['item']->track->label() }} — {{ $track['percent'] }}%"
                                            class="w-6 h-1.5 rounded-full {{ $track['target'] <= 0 ? 'bg-zinc-100 dark:bg-zinc-800' : ($track['percent'] >= 100 ? 'bg-emerald-500' : ($track['percent'] > 0 ? 'bg-sky-400' : 'bg-zinc-200 dark:bg-zinc-700')) }}"></span>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($row['arrears'] > 0)
                                    <flux:badge color="amber" size="sm">{{ $row['arrears'] }}</flux:badge>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-zinc-600">—</span>
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
                            <flux:table.cell colspan="6" class="text-center text-zinc-400 py-8">
                                {{ __('لا طلاب يعرضون هنا.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>
</div>
