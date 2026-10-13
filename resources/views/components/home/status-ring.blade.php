@props([
    /** @var list<array{status: \App\Support\StudentStatus, count: int}> */
    'standing',
    'empty' => null,
])

{{-- How the students stand: the three largest statuses as figures, and every
     status as an arc of a half ring around the share taking part. Pointing at
     an arc names it. --}}
@php
    use App\Support\StudentStatus;

    $colors = [
        StudentStatus::Active->value => '#7A2727',
        StudentStatus::Registering->value => '#C9A063',
        StudentStatus::Suspended->value => '#A3A3A3',
        StudentStatus::Inactive->value => '#D4D4D4',
        StudentStatus::Left->value => '#E4D6B6',
    ];
    $half = M_PI * 100;
    $total = array_sum(array_column($standing, 'count'));
    $gap = count($standing) > 1 ? 2.5 : 0;
    $start = 0.0;
    $arcs = [];

    foreach ($standing as $row) {
        $length = $half * $row['count'] / max(1, $total);
        $arcs[] = $row + [
            'color' => $colors[$row['status']->value],
            'dash' => round(max(0, $length - $gap), 2).' 628.32',
            'offset' => round(-$start, 2),
        ];
        $start += $length;
    }

    $active = collect($standing)->firstWhere('status', StudentStatus::Active)['count'] ?? 0;
    $activeShare = $total === 0 ? 0 : (int) round($active / $total * 100);
@endphp

@if ($standing === [])
    <p class="py-12 text-center text-zinc-500">{{ $empty ?? __('لا طلاب بعد.') }}</p>
@else
    <div x-data="{ arc: null }">
        <div class="mt-5 grid grid-cols-3 gap-3">
            @foreach (collect($arcs)->sortByDesc('count')->take(3) as $arc)
                <div class="border-s-[3px] ps-2.5" style="border-color: {{ $arc['color'] }}">
                    <div class="text-sm text-zinc-500">{{ $arc['status']->label() }}</div>
                    <div class="text-[22px] font-bold text-zinc-900 dark:text-white">{{ $arc['count'] }}</div>
                </div>
            @endforeach
        </div>
        <svg viewBox="0 0 240 140" class="mx-auto mt-5 block w-full max-w-[320px]" role="img" aria-label="{{ $activeShare }}% {{ __('مشاركون') }}">
            <circle cx="120" cy="124" r="100" fill="none" stroke-width="30" stroke-dasharray="314.16 628.32" transform="rotate(180 120 124)" class="stroke-[#F7EFE0] dark:stroke-zinc-800" />
            @foreach ($arcs as $i => $arc)
                <circle cx="120" cy="124" r="100" fill="none" stroke="{{ $arc['color'] }}" stroke-width="30" stroke-dasharray="{{ $arc['dash'] }}" stroke-dashoffset="{{ $arc['offset'] }}" transform="rotate(180 120 124)"
                    class="cursor-pointer transition-opacity" x-on:mouseenter="arc = {{ $i }}" x-on:mouseleave="arc = null" x-on:click="arc = arc === {{ $i }} ? null : {{ $i }}"
                    x-bind:class="arc !== null && arc !== {{ $i }} && 'opacity-30'" />
            @endforeach
            <g x-show="arc === null">
                <text x="120" y="104" text-anchor="middle" font-size="30" font-weight="700" class="fill-zinc-900 dark:fill-white">{{ $activeShare }}%</text>
                <text x="120" y="128" text-anchor="middle" font-size="13" class="fill-zinc-500">{{ __('مشاركون') }}</text>
            </g>
            @foreach ($arcs as $i => $arc)
                <g x-cloak x-show="arc === {{ $i }}">
                    <text x="120" y="104" text-anchor="middle" font-size="30" font-weight="700" class="fill-zinc-900 dark:fill-white">{{ $arc['count'] }}</text>
                    <text x="120" y="128" text-anchor="middle" font-size="13" class="fill-zinc-500">{{ $arc['status']->label() }}</text>
                </g>
            @endforeach
        </svg>
    </div>
@endif
