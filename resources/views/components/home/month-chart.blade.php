@props([
    /** @var list<array{label: string, hifz: int, review: int}> oldest first */
    'months',
])

{{-- The last three months as stacked bars, oldest on the right, a faint ribbon
     carrying each layer across to the next month. Pointing at a month shows
     how it splits between new memorisation and review. --}}
@php
    $colors = ['hifz' => '#7A2727', 'review' => '#C9A063'];
    $base = 250;
    $scale = 196 / max(1, ...array_map(fn ($month) => $month['hifz'] + $month['review'], $months));
    $bars = [];

    foreach ($months as $i => $month) {
        $x = [472, 262, 52][$i];
        $top = $base;
        $segments = [];

        foreach (['hifz', 'review'] as $layer) {
            $height = $month[$layer] * $scale;
            $bottom = $top;

            if ($height > 0) {
                $top = $bottom - $height - ($segments === [] ? 0 : 4);
            }

            $segments[$layer] = ['y' => round($top, 1), 'h' => round(max(0, $height), 1), 'rx' => min(8, $height / 2)];
        }

        $total = $month['hifz'] + $month['review'];
        $bars[] = ['x' => $x, 'cx' => $x + 58, 'segments' => $segments, 'total' => $total, 'labelY' => round($top - 12, 1)] + $month;
    }

    $ribbons = [];
    foreach ([[0, 1], [1, 2]] as [$right, $left]) {
        foreach (['hifz', 'review'] as $layer) {
            $a = $bars[$right]['segments'][$layer];
            $b = $bars[$left]['segments'][$layer];

            if ($a['h'] > 0 && $b['h'] > 0) {
                $ribbons[] = ['color' => $colors[$layer], 'points' => implode(' ', [
                    ($bars[$left]['x'] + 116).','.$b['y'], $bars[$right]['x'].','.$a['y'],
                    $bars[$right]['x'].','.($a['y'] + $a['h']), ($bars[$left]['x'] + 116).','.($b['y'] + $b['h']),
                ])];
            }
        }
    }
@endphp

<div x-data="{ month: null }" class="relative">
    <svg viewBox="0 0 640 292" class="block h-auto w-full" role="img" aria-label="{{ __('الحفظ والمراجعة في الأشهر الثلاثة الأخيرة') }}">
        <g opacity="0.13">
            @foreach ($ribbons as $ribbon)
                <polygon points="{{ $ribbon['points'] }}" fill="{{ $ribbon['color'] }}" />
            @endforeach
        </g>
        @foreach ($bars as $i => $bar)
            <g x-on:mouseenter="month = {{ $i }}" x-on:mouseleave="month = null" x-on:click="month = month === {{ $i }} ? null : {{ $i }}"
                class="cursor-pointer transition-opacity" x-bind:class="month !== null && month !== {{ $i }} && 'opacity-40'">
                <rect x="{{ $bar['x'] }}" y="20" width="116" height="270" fill="transparent" />
                @if ($bar['total'] === 0)
                    <rect x="{{ $bar['x'] }}" y="244" width="116" height="6" rx="3" class="fill-[#F2EBDA] dark:fill-zinc-800" />
                @endif
                @foreach ($bar['segments'] as $layer => $segment)
                    @if ($segment['h'] > 0)
                        <rect x="{{ $bar['x'] }}" y="{{ $segment['y'] }}" width="116" height="{{ $segment['h'] }}" rx="{{ $segment['rx'] }}" fill="{{ $colors[$layer] }}" />
                    @endif
                @endforeach
                <text x="{{ $bar['cx'] }}" y="{{ $bar['total'] === 0 ? 232 : $bar['labelY'] }}" text-anchor="middle" font-size="15" font-weight="600" class="fill-zinc-900 dark:fill-white">{{ $bar['total'] === 0 ? __('لا حفظ') : $bar['total'].' '.__('صفحة') }}</text>
                <text x="{{ $bar['cx'] }}" y="282" text-anchor="middle" font-size="14" class="fill-zinc-500">{{ $bar['label'] }}</text>
            </g>
        @endforeach
    </svg>

    @foreach ($bars as $i => $bar)
        <div x-cloak x-show="month === {{ $i }}" x-transition.opacity
            class="pointer-events-none absolute top-2 z-10 w-44 -translate-x-1/2 rounded-xl border border-zinc-200 bg-white p-3 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
            style="left: {{ round($bar['cx'] / 640 * 100, 2) }}%">
            <div class="font-semibold text-zinc-900 dark:text-white">{{ $bar['label'] }}</div>
            <div class="mt-1.5 flex items-center justify-between gap-2 text-zinc-600 dark:text-zinc-300">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#7A2727]"></span>{{ __('حفظ جديد') }}</span>
                <span class="font-semibold tabular-nums">{{ $bar['hifz'] }}</span>
            </div>
            <div class="mt-1 flex items-center justify-between gap-2 text-zinc-600 dark:text-zinc-300">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#C9A063]"></span>{{ __('مراجعة') }}</span>
                <span class="font-semibold tabular-nums">{{ $bar['review'] }}</span>
            </div>
        </div>
    @endforeach
</div>

<div class="mt-3 flex justify-center gap-6 text-sm text-zinc-600 dark:text-zinc-300">
    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#7A2727]"></span>{{ __('حفظ جديد') }}</span>
    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#C9A063]"></span>{{ __('مراجعة') }}</span>
</div>
