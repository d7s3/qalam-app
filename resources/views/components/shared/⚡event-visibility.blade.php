<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Role;
use App\Support\CalendarVisibility;
use App\Support\Scope;
use Livewire\Component;

/**
 * Handing sight of an event to the offices beneath yours.
 *
 * You may pass on only what you hold, and only downward. Withdrawing what you
 * gave withdraws whatever the receiver passed on in turn, because a grant is
 * read as good only while its grantor's is.
 */
new class extends Component
{
    public string $asRole = 'manager';

    public string $search = '';

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
    }

    private function reader(): ?\App\Models\User
    {
        return Scope::forRole($this->asRole)->user();
    }

    public function hand(int $eventId, string $toRole): void
    {
        $event = $this->visibleEvents()->firstWhere('id', $eventId) ?? abort(404);

        $done = CalendarVisibility::grant($event, $this->asRole, $toRole, $this->reader());

        Flux::toast(
            $done ? __('مُنحت الرؤية') : __('لا تملك أن تمنح هذا'),
            variant: $done ? 'success' : 'danger',
        );
    }

    public function withdraw(int $eventId, string $toRole): void
    {
        $event = $this->visibleEvents()->firstWhere('id', $eventId) ?? abort(404);

        CalendarVisibility::revoke($event, $this->asRole, $toRole);

        Flux::toast(__('سُحبت الرؤية، ومعها ما بُني عليها'), variant: 'success');
    }

    /** @return \Illuminate\Support\Collection<int, AcademicCalendarEvent> */
    private function visibleEvents()
    {
        $user = $this->reader();

        return AcademicCalendarEvent::query()
            ->when($this->search !== '', fn ($q) => $q->where('event_name', 'like', '%'.$this->search.'%'))
            ->orderByDesc('start_date')
            ->take(60)
            ->get()
            ->filter(fn (AcademicCalendarEvent $event) => CalendarVisibility::canSee($event, $this->asRole, $user))
            ->values();
    }

    public function with(): array
    {
        $canGrantTo = CalendarVisibility::canGrantTo($this->asRole);

        return [
            'events' => $this->visibleEvents(),
            'targets' => Role::whereIn('key', $canGrantTo)->pluck('label', 'key'),
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ __('رؤية الأحداث') }}</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('تمنح ما تراه أنت، لمن هو دونك. وسحبُك يسحب ما بناه من منحته بعدك.') }}
        </flux:subheading>
    </div>

    @if($targets->isEmpty())
        <flux:card>
            <flux:text class="text-zinc-400">{{ __('منصبك لا يحمل منصباً آخر، فليس لك من تمنحه.') }}</flux:text>
        </flux:card>
    @else
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="{{ __('ابحث باسم الحدث...') }}" />

        <div class="space-y-3">
            @forelse($events as $event)
                <flux:card wire:key="event-{{ $event->id }}" class="space-y-3">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $event->event_name }}</div>
                            <div class="text-xs text-zinc-400 mt-1">
                                <x-hijri-date :date="$event->start_date" />
                                @unless(\App\Support\CalendarVisibility::governs($event))
                                    · <span>{{ __('لم يُقيَّد بعد — يظهر كما كان') }}</span>
                                @endunless
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            @foreach($targets as $key => $label)
                                @php
                                    $held = \App\Support\CalendarVisibility::canSee($event, $key);
                                @endphp
                                <button
                                    wire:click="{{ $held ? 'withdraw' : 'hand' }}({{ $event->id }}, '{{ $key }}')"
                                    wire:key="grant-{{ $event->id }}-{{ $key }}"
                                    class="px-3 py-1.5 text-xs font-bold rounded-lg border transition-colors
                                        {{ $held
                                            ? 'bg-emerald-600 text-white border-emerald-600'
                                            : 'border-zinc-200 dark:border-zinc-700 text-zinc-500 hover:border-maroon' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:card>
                    <flux:text class="text-zinc-400">{{ __('لا أحداث تراها.') }}</flux:text>
                </flux:card>
            @endforelse
        </div>
    @endif
</div>
