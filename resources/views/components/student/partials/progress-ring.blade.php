@props([
    'percentage' => 0,
    'size' => 96,
    'strokeWidth' => 8,
    'trackClass' => 'text-zinc-100 dark:text-zinc-800',
    'progressClass' => 'text-maroon',
])

@php
    $radius = ($size - $strokeWidth) / 2;
    $circumference = 2 * M_PI * $radius;
    $pct = max(0, min(100, (float) $percentage));
    $offset = $circumference - ($pct / 100) * $circumference;
@endphp

<div class="relative inline-flex items-center justify-center" style="width: {{ $size }}px; height: {{ $size }}px;">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" class="-rotate-90">
        <circle
            cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
            fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}"
            class="{{ $trackClass }}" />
        <circle
            cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
            fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}"
            stroke-linecap="round"
            stroke-dasharray="{{ $circumference }}"
            stroke-dashoffset="{{ $offset }}"
            class="{{ $progressClass }} transition-all duration-700 ease-out" />
    </svg>
    <div class="absolute inset-0 flex items-center justify-center">
        {{ $slot }}
    </div>
</div>
