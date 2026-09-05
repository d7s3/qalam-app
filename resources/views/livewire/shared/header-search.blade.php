<div class="relative w-full max-w-sm" x-data="{ open: false }" x-on:click.outside="open = false">
    <div class="relative">
        <flux:icon icon="magnifying-glass" class="absolute right-3 top-1/2 -translate-y-1/2 size-4 text-zinc-400 pointer-events-none" />
        <input
            type="text"
            wire:model.live.debounce.300ms="query"
            x-on:focus="open = true"
            placeholder="{{ __('ابحث عن طالب أو دفعة...') }}"
            class="w-full rounded-full border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2 pr-9 pl-4 text-sm text-zinc-700 dark:text-zinc-200 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-maroon/30"
        />
    </div>

    @php($results = $this->results())

    <div x-show="open" x-cloak
        class="absolute z-50 mt-2 w-full rounded-xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden">
        @if(mb_strlen(trim($query)) < 2)
            <p class="px-4 py-3 text-sm text-zinc-400">{{ __('اكتب حرفين على الأقل للبحث') }}</p>
        @elseif($results['students']->isEmpty() && $results['circles']->isEmpty())
            <p class="px-4 py-3 text-sm text-zinc-400">{{ __('لا توجد نتائج') }}</p>
        @else
            @if($results['students']->isNotEmpty())
                <div class="px-3 pt-2 pb-1 text-xs font-bold text-zinc-400">{{ __('الطلاب') }}</div>
                @foreach($results['students'] as $student)
                    <a href="{{ $this->studentUrl($student) }}" wire:navigate
                        class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-200">
                        <flux:icon icon="user" class="size-4 text-zinc-400" />
                        {{ $student->name }}
                    </a>
                @endforeach
            @endif

            @if($results['circles']->isNotEmpty())
                <div class="px-3 pt-2 pb-1 text-xs font-bold text-zinc-400 border-t border-zinc-100 dark:border-zinc-800">{{ __('الدفعات') }}</div>
                @foreach($results['circles'] as $circle)
                    <a href="{{ $this->circleUrl($circle) }}" wire:navigate
                        class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-200">
                        <flux:icon icon="circle-stack" class="size-4 text-zinc-400" />
                        {{ $circle->name }}
                    </a>
                @endforeach
            @endif
        @endif
    </div>
</div>
