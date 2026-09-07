{{--
    Nothing to show, said quietly.

    An empty state used to be a tinted panel with a coloured border and an icon
    in a circle — the same weight as a warning, for the ordinary fact that the
    day has not been recorded yet. Emptiness is not an event; it should sit
    lightly on the page and leave the eye free for what is there.
--}}
@props(['icon' => 'clock', 'title' => null])

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-800 px-5 py-6']) }}>
    <flux:icon :icon="$icon" class="mt-0.5 size-5 shrink-0 text-zinc-300 dark:text-zinc-600" />

    <div class="min-w-0 text-sm">
        @if ($title)
            <p class="font-bold text-zinc-600 dark:text-zinc-300">{{ $title }}</p>
        @endif

        <p class="text-zinc-500 dark:text-zinc-400 {{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</p>
    </div>
</div>
