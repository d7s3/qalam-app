@props([
    /** @var list<array{label: string, date: string, pages: int, upcoming: bool}> */
    'days',
])

{{-- Each meeting day of this week as a bar: the best day marked, the days to
     come drawn dashed, and every day's pages shown on pointing at it. --}}
@php
    $tallest = max(1, ...array_column($days, 'pages'));
@endphp

<div class="mt-5 flex min-h-[230px] flex-1 items-end gap-3" role="img" aria-label="{{ __('صفحات الحفظ في كل يوم من أيام هذا الأسبوع') }}">
    @foreach ($days as $day)
        @php
            $best = $day['pages'] > 0 && $day['pages'] === $tallest;
        @endphp
        <div wire:key="day-{{ $day['date'] }}" class="group relative flex flex-1 flex-col items-center gap-2">
            <span @class([
                'text-sm font-semibold tabular-nums text-zinc-900 transition-opacity dark:text-white',
                'opacity-0 group-hover:opacity-100' => ! $best,
            ])>{{ $day['upcoming'] ? '—' : $day['pages'] }}</span>
            <div @class([
                'w-full rounded-[10px] transition-colors',
                'bg-gradient-to-b from-[#7A2727] to-[#C48A8A]' => $best,
                'border border-dashed border-[#E4D6B6] bg-transparent dark:border-zinc-700' => ! $best && $day['upcoming'],
                'bg-[#F2EBDA] group-hover:bg-[#E4D6B6] dark:bg-zinc-800 dark:group-hover:bg-zinc-700' => ! $best && ! $day['upcoming'],
            ]) style="height: {{ $day['pages'] > 0 ? max(8, (int) round($day['pages'] / $tallest * 180)) : 8 }}px"></div>
            <span @class(['text-xs', 'font-semibold text-zinc-900 dark:text-white' => $best, 'text-zinc-500' => ! $best])>{{ $day['label'] }}</span>
        </div>
    @endforeach
</div>
