<?php

use App\Models\Ayah;
use App\Models\Surah;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $query = '';

    #[Computed]
    public function surahs()
    {
        if (mb_strlen($this->query) < 2) {
            return collect();
        }

        return Surah::where('name_arabic', 'like', "%{$this->query}%")
            ->orderBy('number')
            ->limit(5)
            ->get(['id', 'number', 'name_arabic']);
    }

    #[Computed]
    public function ayahs()
    {
        if (mb_strlen($this->query) < 3) {
            return collect();
        }

        return Ayah::with('surah')
            ->where('text_uthmani', 'like', "%{$this->query}%")
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function teachers()
    {
        if (mb_strlen($this->query) < 2) {
            return collect();
        }

        $student = Auth::guard('student')->user();

        if (! $student->circle_id) {
            return collect();
        }

        return Teacher::whereHas('circles', fn ($q) => $q->where('circles.id', $student->circle_id))
            ->where('name', 'like', "%{$this->query}%")
            ->limit(5)
            ->get(['id', 'name']);
    }

    public function hasResults(): bool
    {
        return $this->surahs->isNotEmpty() || $this->ayahs->isNotEmpty() || $this->teachers->isNotEmpty();
    }
};
?>

<div class="relative w-full" x-data="{ open: false }" @click.outside="open = false">
    <div class="relative">
        <flux:icon icon="magnifying-glass" class="absolute top-1/2 -translate-y-1/2 right-3 size-4 text-zinc-400 pointer-events-none" />
        <input
            type="text"
            wire:model.live.debounce.300ms="query"
            @focus="open = true"
            placeholder="{{ __('ابحث عن سورة، آية، أو معلم...') }}"
            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-2.5 pr-10 pl-4 text-sm text-zinc-700 dark:text-zinc-200 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-maroon/30 focus:border-maroon"
        />
    </div>

    @if(mb_strlen($query) >= 2)
        <div x-show="open" x-cloak class="absolute z-30 mt-2 w-full max-h-96 overflow-y-auto bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 rounded-xl shadow-lg divide-y divide-zinc-100 dark:divide-zinc-800">
            @if($this->hasResults())
                @if($this->surahs->isNotEmpty())
                    <div class="p-2">
                        <div class="px-2 py-1 text-xs font-bold text-zinc-400">{{ __('سور') }}</div>
                        @foreach($this->surahs as $surah)
                            <div class="flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 text-sm text-zinc-700 dark:text-zinc-200">
                                <flux:icon icon="book-open" class="size-4 text-maroon shrink-0" />
                                {{ $surah->name_arabic }}
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($this->ayahs->isNotEmpty())
                    <div class="p-2">
                        <div class="px-2 py-1 text-xs font-bold text-zinc-400">{{ __('آيات') }}</div>
                        @foreach($this->ayahs as $ayah)
                            <div class="flex items-start gap-2 px-2 py-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 text-sm text-zinc-700 dark:text-zinc-200">
                                <flux:icon icon="bookmark" class="size-4 text-maroon shrink-0 mt-0.5" />
                                <div>
                                    <div class="truncate">{{ $ayah->text_uthmani }}</div>
                                    <div class="text-xs text-zinc-400">{{ $ayah->surah->name_arabic }} - {{ __('آية') }} {{ $ayah->verse_number }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($this->teachers->isNotEmpty())
                    <div class="p-2">
                        <div class="px-2 py-1 text-xs font-bold text-zinc-400">{{ __('معلمون') }}</div>
                        @foreach($this->teachers as $teacher)
                            <div class="flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 text-sm text-zinc-700 dark:text-zinc-200">
                                <flux:icon icon="user" class="size-4 text-maroon shrink-0" />
                                {{ $teacher->name }}
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="p-4 text-center text-sm text-zinc-400">{{ __('لا توجد نتائج') }}</div>
            @endif
        </div>
    @endif
</div>
