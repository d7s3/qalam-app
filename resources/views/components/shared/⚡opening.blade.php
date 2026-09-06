<?php

use App\Models\PortalMessage;
use App\Services\PortalService;
use App\Support\Scope;
use Livewire\Component;

/**
 * What meets a person when he opens the application.
 *
 * A word somebody addressed to him and he has not read, and — when there is no
 * word — something worth meeting whoever he is. One at a time: two notices on
 * opening is an interruption rather than a greeting.
 */
new class extends Component
{
    public string $asRole = 'student';

    public bool $dismissed = false;

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
            'motivation' => $message ? null : PortalService::motivationFor($user),
        ];
    }
};
?>

<div dir="rtl">
    @if($message)
        <div class="fixed inset-0 z-50 flex items-end md:items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900 shadow-xl p-6 space-y-4">
                @if($message->title)
                    <div class="text-lg font-bold text-zinc-900 dark:text-white">{{ $message->title }}</div>
                @endif

                <div class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-200 whitespace-pre-line">{{ $message->body }}</div>

                <div class="flex items-center justify-between gap-4 pt-2">
                    <span class="text-xs text-zinc-400">
                        {{ $message->attribution() ?? __('من الإدارة') }}
                    </span>
                    <flux:button size="sm" variant="primary" class="!bg-maroon hover:!bg-burgundy"
                        wire:click="acknowledge({{ $message->id }})">
                        {{ __('قرأتها') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @elseif($motivation)
        <div class="fixed inset-0 z-50 flex items-end md:items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900 shadow-xl p-6 space-y-4">
                <flux:badge size="sm" color="amber">{{ $motivation->kindLabel() }}</flux:badge>

                <div class="text-base leading-loose text-zinc-800 dark:text-zinc-100 whitespace-pre-line">
                    {{ $motivation->text }}
                </div>

                <div class="flex items-center justify-between gap-4 pt-2">
                    <span class="text-xs text-zinc-400">
                        {{ $motivation->source }}
                        @if($motivation->grade)
                            · {{ $motivation->grade }}
                        @endif
                    </span>
                    <flux:button size="sm" variant="ghost" wire:click="dismiss">{{ __('إغلاق') }}</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
