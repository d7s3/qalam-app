<tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
    <td class="p-4">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm {{ $rank === 1 ? 'bg-amber-100 text-amber-600 border border-amber-300' : ($rank === 2 ? 'bg-slate-100 text-slate-600 border border-slate-300' : ($rank === 3 ? 'bg-orange-100 text-orange-600 border border-orange-300' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 border border-zinc-200 dark:border-zinc-700')) }}">
                {{ $rank }}
            </div>
            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $standing['student']->name }}</span>
        </div>
    </td>
    <td class="p-4 text-center font-bold text-lg text-emerald-600 dark:text-emerald-500">
        {{ $standing['score'] }}
    </td>
    @if($leaderboard->settings['manual_claim_enabled'] ?? false)
        <td class="p-4 text-center bg-amber-50/20 dark:bg-amber-900/5 border-r border-l border-zinc-100 dark:border-zinc-800/50">
            @if(($standing['pending_score'] ?? 0) > 0)
                <span class="text-sm font-bold text-amber-600 dark:text-amber-400">+{{ $standing['pending_score'] }}</span>
            @else
                <span class="text-zinc-300 dark:text-zinc-700">-</span>
            @endif
        </td>
    @endif

    <td class="p-4 text-center text-sm font-medium text-zinc-600 dark:text-zinc-400 bg-indigo-50/20 dark:bg-indigo-900/5 border-r border-l border-zinc-100 dark:border-zinc-800/50">
        {{ $standing['details']['hifz'] }}
    </td>
    <td class="p-4 text-center text-sm font-medium text-zinc-600 dark:text-zinc-400 bg-indigo-50/20 dark:bg-indigo-900/5 border-r border-l border-zinc-100 dark:border-zinc-800/50">
        {{ $standing['details']['review'] }}
    </td>
    <td class="p-4 text-center text-sm font-medium text-zinc-600 dark:text-zinc-400 bg-indigo-50/20 dark:bg-indigo-900/5 border-r border-l border-zinc-100 dark:border-zinc-800/50">
        {{ $standing['details']['attendance'] }}
    </td>

    @foreach($leaderboard->criteria as $criterion)
        @php
            $timesEarned = $standing['details']['criteria_counts'][$criterion->id] ?? 0;
            $pointsEarned = $timesEarned * $criterion->points;
        @endphp
        <td class="p-4 text-center bg-emerald-50/20 dark:bg-emerald-900 border-l border-zinc-100 dark:border-zinc-800/50">
            @if($timesEarned > 0)
                <div class="flex flex-col items-center">
                    <span class="text-sm font-bold text-zinc-700 dark:text-zinc-300">{{ $pointsEarned }}</span>
                    <span class="text-[10px] text-zinc-400 bg-white dark:bg-zinc-800 px-1.5 py-0.5 rounded shadow-sm">{{ $timesEarned }} {{ __('مرات') }}</span>
                </div>
            @else
                <span class="text-zinc-300 dark:text-zinc-700">-</span>
            @endif
        </td>
    @endforeach
</tr>
