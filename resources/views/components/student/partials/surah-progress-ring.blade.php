@props(['surah'])

@php
    $statusClass = match ($surah['status']) {
        'full' => 'text-emerald-500',
        'partial' => 'text-amber-500',
        default => 'text-zinc-300 dark:text-zinc-700',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2 shrink-0 w-20']) }}>
    <x-student.partials.progress-ring :percentage="$surah['percentage']" :size="72" :stroke-width="6" progress-class="{{ $statusClass }}">
        <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ $surah['percentage'] }}%</span>
    </x-student.partials.progress-ring>
    <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 truncate w-full text-center">{{ $surah['name'] }}</span>
</div>
