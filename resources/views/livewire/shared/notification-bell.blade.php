<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" wire:poll.30s>
    <button type="button" x-on:click="open = !open; if (open) $wire.markAllRead()"
        class="relative p-2 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800">
        <flux:icon icon="bell" class="size-5 text-zinc-500 dark:text-zinc-300" />
        @if($unreadCount > 0)
            <span class="absolute -top-0.5 -left-0.5 flex items-center justify-center size-4 rounded-full bg-rose-500 text-white text-[10px] font-bold leading-none">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak
        class="absolute left-0 z-50 mt-2 w-80 rounded-xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 font-bold text-sm text-zinc-800 dark:text-zinc-100">
            {{ __('الإشعارات') }}
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse($notifications as $notification)
                <a href="{{ $notification->url ?? '#' }}" wire:navigate
                    class="block px-4 py-3 border-b border-zinc-50 dark:border-zinc-800/60 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $notification->title }}</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $notification->body }}</div>
                    <div class="text-[10px] text-zinc-400 mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                </a>
            @empty
                <p class="text-sm text-zinc-400 text-center py-8">{{ __('لا توجد إشعارات بعد') }}</p>
            @endforelse
        </div>
    </div>
</div>
