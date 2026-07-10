@props([
    'icon',
    'label',
    'value',
    'suffix' => null,
    'accentClass' => 'bg-maroon/10 text-maroon',
    'empty' => false,
    'emptyText' => null,
])

<div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-5 flex items-center gap-4">
    <div class="shrink-0 size-12 rounded-xl flex items-center justify-center {{ $accentClass }}">
        <flux:icon :icon="$icon" variant="solid" class="size-6" />
    </div>
    <div class="min-w-0">
        <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ $label }}</div>
        @if($empty)
            <div class="text-sm font-semibold text-zinc-400 dark:text-zinc-500 mt-0.5">{{ $emptyText ?? __('لا توجد بيانات بعد') }}</div>
        @else
            <div class="text-2xl font-extrabold text-zinc-900 dark:text-zinc-100 mt-0.5">
                {{ $value }}
                @if($suffix)
                    <span class="text-sm font-medium text-zinc-400">{{ $suffix }}</span>
                @endif
            </div>
        @endif
    </div>
</div>
