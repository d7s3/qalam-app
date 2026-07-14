@props(['percentage' => 0])

@php
    $pct = max(0, min(100, (float) $percentage));
@endphp

<div>
    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 mb-1">
        <span>{{ __('نسبة الإنجاز') }}</span>
        <span class="font-bold text-zinc-700 dark:text-zinc-200">{{ $pct }}%</span>
    </div>
    <div class="w-full h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
        <div class="h-full rounded-full bg-maroon transition-all duration-700 ease-out" style="width: {{ $pct }}%"></div>
    </div>
</div>
