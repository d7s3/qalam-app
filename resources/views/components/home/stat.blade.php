@props([
    'icon',
    'label',
    'value',
    'change' => null,
    'caption' => null,
    'href' => null,
])

{{-- One figure on a home page, as the October design draws it: an icon in a
     quiet tile, the label, the figure large, its movement, and a line under. --}}
<div {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 shadow-[0_1px_2px_rgba(63,26,25,.04)] dark:border-zinc-800 dark:bg-zinc-900') }}>
    <div class="flex items-center gap-2.5">
        <span class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
            <flux:icon :icon="$icon" variant="mini" class="size-4" />
        </span>
        <span class="text-sm font-medium leading-tight text-zinc-700 sm:text-base dark:text-zinc-200">{{ $label }}</span>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 sm:mt-4">
        <span class="text-[28px] font-bold sm:text-[34px] leading-none tracking-tight text-zinc-900 dark:text-white">{{ $value }}</span>
        <x-home.change :change="$change" />
    </div>
    @if ($caption || $href)
        <p class="mt-2 text-sm text-zinc-500">
            @if ($href)
                <a href="{{ $href }}" wire:navigate class="font-medium text-maroon hover:underline dark:text-red-secondary">{{ $caption }}</a>
            @else
                {{ $caption }}
            @endif
        </p>
    @endif
</div>
