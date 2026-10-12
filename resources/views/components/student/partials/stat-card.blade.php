@props([
    'icon',
    'label',
    'value',
    'suffix' => null,
    /** Kept for the callers that pass it; the October design draws every tile in one quiet tone. */
    'accentClass' => null,
    'empty' => false,
    'emptyText' => null,
])

{{-- A student's figure, drawn as every home page draws one: an icon in a
     quiet tile beside its label, and the figure large beneath. --}}
<div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-[0_1px_2px_rgba(63,26,25,.04)] dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex min-w-0 items-center gap-2.5">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
            <flux:icon :icon="$icon" variant="mini" class="size-4" />
        </span>
        <span class="truncate font-medium text-zinc-700 dark:text-zinc-200">{{ $label }}</span>
    </div>
    @if($empty)
        <div class="mt-4 text-base font-semibold text-zinc-400 dark:text-zinc-500">{{ $emptyText ?? __('لا توجد بيانات بعد') }}</div>
    @else
        <div class="mt-4 text-[30px] font-bold leading-none tracking-tight text-zinc-900 dark:text-white">
            {{ $value }}
            @if($suffix)
                <span class="text-sm font-medium text-zinc-500">{{ $suffix }}</span>
            @endif
        </div>
    @endif
</div>
