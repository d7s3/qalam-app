<?php

use App\Models\Motivation;
use App\Services\PortalService;
use App\Support\Scope;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * What meets a person when he opens the application.
 *
 * A word somebody addressed to him and he has not read, and — when there is no
 * word — something worth meeting whoever he is. One at a time: two notices on
 * opening is an interruption rather than a greeting.
 *
 * The shell mounts this on every page, so the greeting is drawn once a sitting
 * and kept in the session: drawn afresh over each page, it was a nag that
 * covered every screen he moved to. A word addressed to him still waits on
 * every page until he says he read it.
 */
new class extends Component
{
    public string $asRole = 'student';

    public bool $dismissed = false;

    /** The greeting drawn for this sitting, kept through the page's re-renders. */
    #[Locked]
    public ?int $motivationId = null;

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
    }

    private function reader(): ?\App\Models\User
    {
        return Scope::forRole($this->asRole)->user();
    }

    public function acknowledge(int $messageId): void
    {
        $user = $this->reader() ?? abort(403);

        $message = PortalService::waitingFor($user, $this->asRole)->firstWhere('id', $messageId) ?? abort(404);

        PortalService::markRead($message, $user);
    }

    public function dismiss(): void
    {
        $this->dismissed = true;
    }

    public function with(): array
    {
        $user = $this->reader();

        if (! $user || $this->dismissed) {
            return ['message' => null, 'motivation' => null];
        }

        $message = PortalService::waitingFor($user, $this->asRole)->first();

        return [
            'message' => $message,
            // Only when nothing was said to him — a greeting, not a queue.
            'motivation' => $message ? null : $this->greeting($user),
        ];
    }

    private function greeting(\App\Models\User $user): ?Motivation
    {
        if ($this->motivationId) {
            return Motivation::find($this->motivationId);
        }

        $greeted = 'opening.greeted.'.$this->asRole;

        if (session()->has($greeted)) {
            return null;
        }

        $motivation = PortalService::motivationFor($user);

        session()->put($greeted, true);
        $this->motivationId = $motivation?->id;

        return $motivation;
    }
};
?>

{{-- However long the word, the notice keeps to the screen it is read on: its
     heading and its button stay put and only the text between them scrolls. --}}
<div dir="rtl">
    @if($message || $motivation)
        {{-- Above the bottom bars (the student's sits at 9999), which used to
             hide the notice's button behind them on a phone. --}}
        <div class="fixed inset-0 z-[10000] flex items-end md:items-center justify-center bg-black/40 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:p-4"
            role="dialog" aria-modal="true"
            @unless($message)
                wire:click.self="dismiss" x-on:keydown.escape.window="$wire.dismiss()"
            @endunless
        >
            <div class="flex max-h-[85dvh] w-full max-w-lg flex-col gap-4 rounded-2xl bg-white p-5 shadow-xl sm:p-6 dark:bg-zinc-900">
                @if($message)
                    @if($message->title)
                        <div class="shrink-0 text-lg font-bold text-zinc-900 dark:text-white">{{ $message->title }}</div>
                    @endif

                    <div class="min-h-0 overflow-y-auto overscroll-contain text-sm leading-relaxed text-zinc-700 dark:text-zinc-200 whitespace-pre-line">{{ trim($message->body) }}</div>

                    <div class="flex shrink-0 items-center justify-between gap-4 pt-2">
                        <span class="text-xs text-zinc-400">
                            {{ $message->attribution() ?? __('من الإدارة') }}
                        </span>
                        <flux:button size="sm" variant="primary" class="!bg-maroon hover:!bg-burgundy"
                            wire:click="acknowledge({{ $message->id }})">
                            {{ __('قرأتها') }}
                        </flux:button>
                    </div>
                @else
                    <flux:badge size="sm" color="amber" class="self-start shrink-0">{{ $motivation->kindLabel() }}</flux:badge>

                    <div class="min-h-0 overflow-y-auto overscroll-contain text-base leading-loose text-zinc-800 dark:text-zinc-100 whitespace-pre-line">{{ trim($motivation->text) }}</div>

                    <div class="flex shrink-0 items-center justify-between gap-4 pt-2">
                        <span class="text-xs text-zinc-400">
                            {{ $motivation->source }}
                            @if($motivation->grade)
                                · {{ $motivation->grade }}
                            @endif
                        </span>
                        <flux:button size="sm" variant="ghost" wire:click="dismiss">{{ __('إغلاق') }}</flux:button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
