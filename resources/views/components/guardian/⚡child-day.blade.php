<?php

use App\Models\PeriodValue;
use App\Models\Student;
use App\Services\CompensationService;
use App\Services\DayAgendaService;
use App\Support\HijriSeasons;
use App\Support\Scope;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * The family's window onto the same day.
 *
 * A guardian had reports and no rhythm — figures after the fact, and nothing
 * that said what this week is about or what to reinforce at home tonight. This
 * is his son's day as his son sees it, and nothing more: he watches, and does
 * not answer for him. Recording attendance and finishing tasks belong to the
 * person they are about.
 */
new class extends Component
{
    public ?int $childId = null;

    public string $date = '';

    public function mount(): void
    {
        $this->date = now('Asia/Riyadh')->format('Y-m-d');
        $this->childId = $this->children()->first()?->id;
    }

    public function shift(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->format('Y-m-d');
    }

    /** @return \Illuminate\Support\Collection<int, Student> */
    private function children()
    {
        $guardian = Scope::forRole('guardian')->user();

        return $guardian
            ? Student::where('guardian_id', $guardian->id)->orderBy('name')->get()
            : collect();
    }

    public function with(): array
    {
        $children = $this->children();
        $child = $children->firstWhere('id', $this->childId) ?? $children->first();

        if (! $child) {
            return ['children' => $children, 'child' => null, 'agenda' => null,
                'values' => collect(), 'seasons' => [], 'debts' => collect(), 'losses' => []];
        }

        return [
            'children' => $children,
            'child' => $child,
            'agenda' => DayAgendaService::forUser($child, 'student', $this->date),
            'seasons' => HijriSeasons::on($this->date),
            'values' => PeriodValue::runningOn($this->date, $child->stage_id, $child->circle_id)->get(),
            'debts' => CompensationService::openFor($child),
            'losses' => App\Services\EducationalLossService::formative(
                $child,
                'student',
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
            <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ __('يوم ابني') }}</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
                <x-hijri-date :date="$date" />
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(-1)" />
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(1)" />
        </div>
    </div>

    @if($children->count() > 1)
        <div class="flex items-center gap-2 flex-wrap">
            @foreach($children as $one)
                <button wire:click="$set('childId', {{ $one->id }})" wire:key="child-{{ $one->id }}"
                    class="px-4 py-2 text-sm font-bold rounded-lg border transition-colors
                        {{ $child?->id === $one->id ? 'bg-maroon text-white border-maroon' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
                    {{ $one->name }}
                </button>
            @endforeach
        </div>
    @endif

    @if(! $child)
        <flux:card><flux:text class="text-zinc-400">{{ __('لا يوجد أبناء مرتبطون بحسابك.') }}</flux:text></flux:card>
    @else
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

        @if($values->isNotEmpty())
            <flux:card class="space-y-3 border-maroon/30">
                <flux:heading size="lg">{{ __('ما يعملون عليه') }}</flux:heading>
                @foreach($values as $value)
                    <div wire:key="value-{{ $value->id }}" class="space-y-1">
                        <div class="text-base font-bold text-maroon dark:text-red-secondary">{{ $value->title }}</div>
                        @if($value->practice)
                            <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ $value->practice }}</div>
                        @endif
                    </div>
                @endforeach
                <flux:text class="text-xs text-zinc-400">{{ __('ما يُعزَّز في البيت.') }}</flux:text>
            </flux:card>
        @endif

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('مواعيده اليوم') }}</flux:heading>
            @forelse($agenda['occurrences'] as $row)
                <div class="flex items-center justify-between py-2.5 border-b border-zinc-50 dark:border-zinc-800/60 last:border-0"
                     wire:key="occ-{{ $row['event']->id }}">
                    <div>
                        <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $row['event']->event_name }}</div>
                        @if($row['event']->formative_note)
                            <div class="text-xs text-zinc-500">{{ $row['event']->formative_note }}</div>
                        @endif
                    </div>
                    @if($row['status'])
                        <flux:badge size="sm" :color="in_array($row['status'], ['present','late']) ? 'lime' : 'rose'">
                            {{ ['present' => __('حاضر'), 'late' => __('متأخر'), 'absent' => __('غائب'), 'excused' => __('معذور')][$row['status']] ?? $row['status'] }}
                        </flux:badge>
                    @else
                        <flux:badge size="sm" color="zinc">{{ __('لم يُسجَّل') }}</flux:badge>
                    @endif
                </div>
            @empty
                <flux:text class="text-zinc-400">{{ __('لا مواعيد اليوم.') }}</flux:text>
            @endforelse
        </flux:card>

        @if($agenda['content'] !== [])
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('عمله اليوم') }}</flux:heading>
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

        @if($debts->isNotEmpty())
            <flux:card class="space-y-3 border-amber-200 dark:border-amber-900/50">
                <flux:heading size="lg">{{ __('عليه تعويضه') }}</flux:heading>
                <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                    @foreach($debts as $debt)
                        <div class="flex items-center justify-between py-2.5" wire:key="debt-{{ $debt->id }}">
                            <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $debt->label }}</span>
                            <span class="text-xs text-zinc-400"><x-hijri-date :date="$debt->original_date" /></span>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        @if($losses !== [])
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('فاته هذا الأسبوع') }}</flux:heading>
                <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                    @foreach($losses as $loss)
                        <div class="flex items-center justify-between py-2.5" wire:key="loss-{{ $loss['event']->id }}-{{ $loss['date'] }}">
                            <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $loss['event']->event_name }}</span>
                            <span class="text-xs text-zinc-400"><x-hijri-date :date="$loss['date']" /></span>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif
    @endif
</div>
