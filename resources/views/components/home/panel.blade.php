@props([
    'icon',
    'title',
    'count' => null,
    /** 'maroon' marks a panel that asks for something to be done. */
    'tone' => 'neutral',
])

{{-- A section of a home page: its icon tile and title, an optional count and
     actions on the far side, then whatever it holds. --}}
<section {{ $attributes->class('min-w-0 rounded-2xl border border-zinc-200 bg-white p-5 shadow-[0_1px_2px_rgba(63,26,25,.04)] dark:border-zinc-800 dark:bg-zinc-900') }}>
    <div class="flex flex-wrap items-center gap-2.5">
        <span @class([
            'flex size-8 items-center justify-center rounded-lg',
            'bg-maroon/10 text-maroon dark:text-red-secondary' => $tone === 'maroon',
            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $tone !== 'maroon',
        ])>
            <flux:icon :icon="$icon" variant="mini" class="size-4" />
        </span>
        <h2 class="font-medium text-zinc-700 dark:text-zinc-200">{{ $title }}</h2>
        @if ($count)
            <span class="rounded-full bg-maroon/10 px-2.5 py-0.5 text-sm font-semibold text-maroon dark:text-red-secondary">{{ $count }}</span>
        @endif
        @isset($actions)
            <div class="ms-auto flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
    {{ $slot }}
</section>
