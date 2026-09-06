<?php

use App\Models\SelfProgramTrack;
use App\Models\SelfProgramTrackExclusion;
use App\Models\Stage;
use App\Support\Scope;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Which fields the self programme is made of, and which are set aside.
 *
 * The five were fixed in code once. They are rows now: a sixth may be added, and
 * any may be set aside — for a term, or indefinitely — without the weeks that
 * already used it losing their meaning. A system field is never deleted for
 * exactly that reason; it is set aside instead.
 */
new class extends Component
{
    public string $asRole = 'manager';

    public string $newLabel = '';

    public string $newUnit = '';

    public ?int $asideTrackId = null;

    public ?int $asideStageId = null;

    public string $asideFrom = '';

    public string $asideTo = '';

    public string $asideReason = '';

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
    }

    private function scope(): Scope
    {
        return Scope::forRole($this->asRole);
    }

    public function addTrack(): void
    {
        $this->validate([
            'newLabel' => ['required', 'string', 'max:60'],
            'newUnit' => ['nullable', 'string', 'max:30'],
        ], [], ['newLabel' => __('اسم المجال')]);

        $key = Str::slug($this->newLabel, '_') ?: 'track_'.Str::random(6);

        if (SelfProgramTrack::where('key', $key)->exists()) {
            $this->addError('newLabel', __('يوجد مجال بهذا الاسم.'));

            return;
        }

        SelfProgramTrack::create([
            'key' => $key,
            'label' => $this->newLabel,
            'default_unit' => $this->newUnit ?: 'وحدة',
            'is_system' => false,
            'sort_order' => (int) SelfProgramTrack::max('sort_order') + 1,
        ]);

        $this->reset(['newLabel', 'newUnit']);

        Flux::toast(__('أُضيف المجال'), variant: 'success');
    }

    /**
     * Set a field aside.
     *
     * Leaving both dates empty retires it; giving them puts it away for a term
     * and brings it back on its own, with nobody having to remember.
     */
    public function setAside(): void
    {
        $this->validate([
            'asideTrackId' => ['required', 'integer'],
            'asideTo' => ['nullable', 'date', 'after_or_equal:asideFrom'],
        ], [], ['asideTrackId' => __('المجال'), 'asideTo' => __('إلى')]);

        $reach = $this->scope()->stageIds();

        abort_if($this->asideStageId !== null && $reach !== null && ! $reach->contains($this->asideStageId), 403);

        SelfProgramTrackExclusion::create([
            'self_program_track_id' => $this->asideTrackId,
            'stage_id' => $this->asideStageId,
            'starts_on' => $this->asideFrom ?: null,
            'ends_on' => $this->asideTo ?: null,
            'reason' => $this->asideReason ?: null,
            'created_by_id' => $this->scope()->user()?->id,
        ]);

        $this->reset(['asideFrom', 'asideTo', 'asideReason']);

        Flux::toast(__('نُحّي المجال'), variant: 'success');
    }

    public function restore(int $exclusionId): void
    {
        SelfProgramTrackExclusion::findOrFail($exclusionId)->delete();

        Flux::toast(__('أُعيد المجال'), variant: 'success');
    }

    public function with(): array
    {
        $reach = $this->scope()->stageIds();

        return [
            'tracks' => SelfProgramTrack::ordered(),
            'running' => SelfProgramTrack::orderedFor($reach?->first())->pluck('id')->all(),
            'exclusions' => SelfProgramTrackExclusion::with(['track', 'stage'])->latest()->get(),
            'stages' => $reach === null
                ? Stage::orderBy('name')->get()
                : Stage::whereIn('id', $reach)->orderBy('name')->get(),
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ __('مجالات البرنامج الذاتي') }}</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('أضف مجالاً، أو نحِّ واحداً لفترة. المجال المنحّى لا يُطلب من الطالب ولا يُحسب عليه.') }}
        </flux:subheading>
    </div>

    <flux:card class="space-y-3">
        <flux:heading size="sm">{{ __('المجالات') }}</flux:heading>

        <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
            @foreach($tracks as $track)
                <div class="flex items-center justify-between py-3" wire:key="track-{{ $track->id }}">
                    <div class="flex items-center gap-2">
                        <flux:icon :name="$track->icon()" class="size-4 text-zinc-400" />
                        <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $track->label() }}</span>
                        <span class="text-xs text-zinc-400">{{ $track->defaultUnit() }}</span>
                        @if($track->is_system)
                            <flux:badge color="zinc" size="sm">{{ __('أصلي') }}</flux:badge>
                        @endif
                    </div>

                    @if(in_array($track->id, $running, true))
                        <flux:badge color="lime" size="sm">{{ __('يعمل') }}</flux:badge>
                    @else
                        <flux:badge color="amber" size="sm">{{ __('منحّى') }}</flux:badge>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>

    <flux:card class="flex items-end gap-3 flex-wrap">
        <flux:field class="flex-1 min-w-48">
            <flux:label>{{ __('مجال جديد') }}</flux:label>
            <flux:input wire:model="newLabel" placeholder="{{ __('مثال: السيرة') }}" />
            <flux:error name="newLabel" />
        </flux:field>
        <flux:field class="w-40">
            <flux:label>{{ __('وحدته') }}</flux:label>
            <flux:input wire:model="newUnit" placeholder="{{ __('درس') }}" />
        </flux:field>
        <flux:button variant="primary" class="!bg-maroon hover:!bg-burgundy" wire:click="addTrack">
            {{ __('إضافة') }}
        </flux:button>
    </flux:card>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="sm">{{ __('تنحية مجال') }}</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                {{ __('اترك التاريخين فارغين لتنحيته حتى إشعار آخر، أو حدّدهما فيعود وحده.') }}
            </flux:subheading>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <flux:field>
                <flux:label>{{ __('المجال') }}</flux:label>
                <flux:select wire:model="asideTrackId" placeholder="{{ __('اختر...') }}">
                    @foreach($tracks as $track)
                        <flux:select.option value="{{ $track->id }}">{{ $track->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="asideTrackId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('البرنامج') }}</flux:label>
                <flux:select wire:model="asideStageId">
                    <flux:select.option value="">{{ __('كل المركز') }}</flux:select.option>
                    @foreach($stages as $stage)
                        <flux:select.option value="{{ $stage->id }}">{{ $stage->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('من') }}</flux:label>
                <flux:input type="date" wire:model="asideFrom" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('إلى') }}</flux:label>
                <flux:input type="date" wire:model="asideTo" />
                <flux:error name="asideTo" />
            </flux:field>
        </div>

        <div class="flex items-end gap-3">
            <flux:field class="flex-1">
                <flux:label>{{ __('السبب') }}</flux:label>
                <flux:input wire:model="asideReason" placeholder="{{ __('اختياري') }}" />
            </flux:field>
            <flux:button variant="ghost" wire:click="setAside">{{ __('نحِّه') }}</flux:button>
        </div>
    </flux:card>

    @if($exclusions->isNotEmpty())
        <flux:card class="space-y-2">
            <flux:heading size="sm">{{ __('المنحّى حالياً') }}</flux:heading>

            <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                @foreach($exclusions as $exclusion)
                    <div class="flex items-center justify-between py-3" wire:key="ex-{{ $exclusion->id }}">
                        <div>
                            <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">
                                {{ $exclusion->track?->label() }}
                            </div>
                            <div class="text-xs text-zinc-400 flex items-center gap-2 flex-wrap">
                                <span>{{ $exclusion->stage?->name ?? __('كل المركز') }}</span>
                                @if($exclusion->starts_on || $exclusion->ends_on)
                                    <span>·</span>
                                    <x-hijri-date :date="$exclusion->starts_on ?? now()" />
                                    <span>—</span>
                                    <span>{{ $exclusion->ends_on ? '' : __('بلا نهاية') }}</span>
                                    @if($exclusion->ends_on)
                                        <x-hijri-date :date="$exclusion->ends_on" />
                                    @endif
                                @else
                                    <span>· {{ __('حتى إشعار آخر') }}</span>
                                @endif
                                @if($exclusion->reason)
                                    <span>· {{ $exclusion->reason }}</span>
                                @endif
                            </div>
                        </div>

                        <flux:button size="sm" variant="ghost" wire:click="restore({{ $exclusion->id }})">
                            {{ __('أعِده') }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
