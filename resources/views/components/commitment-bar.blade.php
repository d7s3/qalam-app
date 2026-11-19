@props(['percent' => null, 'detail' => null])

{{-- One commitment bar: green at the line and above, amber close under it, red far under. --}}
@if ($percent === null)
    <span class="text-xs text-zinc-400">{{ __('لا شيء مطلوب') }}</span>
@else
    @php
        $colour = $percent >= \App\Services\CommitmentService::THRESHOLD
            ? 'bg-emerald-500'
            : ($percent >= 50 ? 'bg-amber-500' : 'bg-rose-500');
    @endphp
    <div class="min-w-32" data-commitment-bar="{{ $percent }}">
        <div class="flex items-baseline justify-between gap-2 text-xs">
            <span class="font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ \App\Support\SelfProgramUnit::number($percent) }}%</span>
            @if ($detail)
                <span class="text-zinc-500">{{ $detail }}</span>
            @endif
        </div>
        <div class="mt-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
            <div class="h-full rounded-full {{ $colour }}" style="width: {{ min(100, $percent) }}%"></div>
        </div>
    </div>
@endif
