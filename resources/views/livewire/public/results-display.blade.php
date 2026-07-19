<div class="h-screen w-screen flex flex-col"
    data-total="{{ count($slides) }}"
    x-data="{
        current: 0,
        // Read live from the DOM attribute so Livewire re-renders (hiding a
        // list, changing dates) update the count without re-initializing Alpine.
        get total() { return parseInt(this.$root.dataset.total) || 0; },
        auto: false,
        seconds: 15,
        timer: null,
        next() { if (this.total) this.current = (this.current + 1) % this.total; },
        prev() { if (this.total) this.current = (this.current - 1 + this.total) % this.total; },
        toggleAuto() {
            this.auto = ! this.auto;
            clearInterval(this.timer);
            if (this.auto) { this.timer = setInterval(() => this.next(), this.seconds * 1000); }
        },
        goFullscreen() {
            document.fullscreenElement
                ? document.exitFullscreen()
                : document.documentElement.requestFullscreen();
        },
    }"
    x-effect="if (current >= total && total > 0) current = 0"
    @keydown.window.arrow-left="next()"
    @keydown.window.arrow-right="prev()"
    @keydown.window.f.prevent="goFullscreen()">

    {{-- Top bar --}}
    <div class="flex items-center justify-between px-8 py-4 shrink-0">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🏆</span>
            <div>
                <div class="text-xl font-bold">{{ $leaderboard->title }}</div>
                <div class="text-xs text-zinc-500">شاشة النتائج</div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <template x-if="total">
                <span class="text-sm text-zinc-500 tabular-nums" x-text="`${current + 1} / ${total}`"></span>
            </template>
            <button type="button" class="p-2 rounded-lg hover:bg-white/10 transition"
                :class="auto && 'text-emerald-400'"
                @click="toggleAuto()" title="تقليب تلقائي">
                <flux:icon icon="play-circle" class="size-6" />
            </button>
            <button type="button" class="p-2 rounded-lg hover:bg-white/10 transition"
                @click="goFullscreen()" title="ملء الشاشة (F)">
                <flux:icon icon="arrows-pointing-out" class="size-6" />
            </button>
            <button type="button" class="p-2 rounded-lg hover:bg-white/10 transition"
                wire:click="$toggle('settingsOpen')" title="الإعدادات">
                <flux:icon icon="cog-6-tooth" class="size-6" />
            </button>
        </div>
    </div>

    {{-- Settings panel --}}
    @if($settingsOpen)
        <div class="absolute top-20 left-8 z-30 w-96 max-h-[75vh] overflow-y-auto bg-zinc-900 border border-zinc-700 rounded-2xl p-5 shadow-2xl space-y-4">
            <flux:heading size="sm">إعدادات العرض</flux:heading>

            <div class="grid grid-cols-2 gap-3">
                <flux:input type="date" wire:model.live="endDate" label="آخر يوم محسوب" />
                <flux:input type="date" wire:model.live="attendanceStart" label="بداية فترة الدوام" />
            </div>

            <div>
                <div class="text-sm font-medium mb-2">القوائم المعروضة</div>
                <div class="space-y-1.5">
                    @foreach($allSlides as $slide)
                        <label class="flex items-center gap-2 text-sm cursor-pointer" wire:key="toggle-{{ $slide['key'] }}">
                            <input type="checkbox" class="rounded border-zinc-600 bg-zinc-800"
                                @checked(! in_array($slide['key'], $hiddenSlides, true))
                                wire:click="toggleSlide('{{ $slide['key'] }}')">
                            <span>{{ $slide['title'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="text-sm font-medium block mb-1">مدة التقليب التلقائي (ثانية)</label>
                <input type="number" min="5" max="120" x-model.number="seconds"
                    @change="if (auto) { toggleAuto(); toggleAuto(); }"
                    class="w-24 rounded-lg bg-zinc-800 border border-zinc-600 px-3 py-1.5 text-sm">
            </div>
        </div>
    @endif

    {{-- Slides --}}
    <div class="grow relative px-8 pb-6 min-h-0">
        @forelse($slides as $index => $slide)
            <div x-show="current === {{ $index }}" x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0"
                class="absolute inset-0 px-8 pb-6 flex flex-col">

                <div class="text-center shrink-0 mb-5">
                    <h1 class="text-4xl md:text-5xl font-extrabold">{{ $slide['title'] }}</h1>
                    <p class="text-zinc-400 mt-2 text-lg">{{ $slide['subtitle'] }}</p>
                </div>

                <div class="grow min-h-0 max-w-5xl w-full mx-auto overflow-hidden">
                    @if(empty($slide['rows']))
                        <div class="h-full flex items-center justify-center text-2xl text-zinc-600">
                            لا توجد نتائج في هذه الفترة
                        </div>
                    @else
                        <div class="grid gap-2.5">
                            @foreach($slide['rows'] as $rank => $row)
                                @php
                                    $medal = ['🥇', '🥈', '🥉'][$rank] ?? null;
                                    $rowClass = match ($rank) {
                                        0 => 'bg-amber-500/15 border-amber-400/40',
                                        1 => 'bg-zinc-400/15 border-zinc-300/40',
                                        2 => 'bg-orange-700/15 border-orange-500/40',
                                        default => 'bg-white/5 border-white/10',
                                    };
                                @endphp
                                <div class="flex items-center gap-4 px-6 py-3 rounded-2xl border {{ $rowClass }}">
                                    <div class="w-12 text-center text-2xl font-black shrink-0">
                                        {{ $medal ?? $rank + 1 }}
                                    </div>
                                    <div class="grow min-w-0">
                                        <div class="text-2xl font-bold truncate">{{ $row['name'] }}</div>
                                        @if($row['circle'] ?? null)
                                            <div class="text-sm text-zinc-400">{{ $row['circle'] }}</div>
                                        @endif
                                    </div>
                                    @if($slide['type'] === 'attendance')
                                        <div class="flex items-center gap-6 shrink-0 text-center">
                                            <div>
                                                <div class="text-2xl font-extrabold text-red-400 tabular-nums">{{ $row['absences'] }}</div>
                                                <div class="text-xs text-zinc-500">غياب</div>
                                            </div>
                                            <div>
                                                <div class="text-2xl font-extrabold text-emerald-400 tabular-nums">{{ $row['presents'] }}</div>
                                                <div class="text-xs text-zinc-500">حضور</div>
                                            </div>
                                            <div>
                                                <div class="text-2xl font-extrabold tabular-nums">{{ $row['percentage'] }}%</div>
                                                <div class="text-xs text-zinc-500">انضباط</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="shrink-0 text-left">
                                            <span class="text-3xl font-extrabold tabular-nums text-amber-300">{{ number_format($row['value']) }}</span>
                                            <span class="text-sm text-zinc-400 mr-1">{{ $slide['unit'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="h-full flex items-center justify-center text-2xl text-zinc-600">
                كل القوائم مخفية — فعّل قائمة واحدة على الأقل من الإعدادات
            </div>
        @endforelse

        {{-- Arrows --}}
        @if(count($slides) > 1)
            <button type="button" @click="next()"
                class="absolute left-3 top-1/2 -translate-y-1/2 z-20 p-3 rounded-full bg-white/10 hover:bg-white/20 transition">
                <flux:icon icon="chevron-left" class="size-8" />
            </button>
            <button type="button" @click="prev()"
                class="absolute right-3 top-1/2 -translate-y-1/2 z-20 p-3 rounded-full bg-white/10 hover:bg-white/20 transition">
                <flux:icon icon="chevron-right" class="size-8" />
            </button>
        @endif
    </div>
</div>
