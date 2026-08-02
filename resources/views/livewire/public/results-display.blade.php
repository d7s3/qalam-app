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
        toggleTheme() {
            const el = document.documentElement;
            el.classList.toggle('dark');
            localStorage.setItem('resultsDisplayTheme', el.classList.contains('dark') ? 'dark' : 'light');
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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-9 text-amber-500 dark:text-amber-400" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0" />
            </svg>
            <div>
                <div class="text-xl font-bold">{{ $leaderboard->title }}</div>
                <div class="text-xs text-zinc-500">شاشة النتائج</div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <template x-if="total">
                <span class="text-sm text-zinc-500 tabular-nums" x-text="`${current + 1} / ${total}`"></span>
            </template>
            <button type="button" class="p-2 rounded-lg hover:bg-zinc-900/10 dark:hover:bg-white/10 transition"
                @click="toggleTheme()" title="تبديل المظهر">
                <flux:icon icon="sun" class="size-6 hidden dark:block" />
                <flux:icon icon="moon" class="size-6 dark:hidden" />
            </button>
            <button type="button" class="p-2 rounded-lg hover:bg-zinc-900/10 dark:hover:bg-white/10 transition"
                :class="auto && 'text-emerald-600 dark:text-emerald-400'"
                @click="toggleAuto()" title="تقليب تلقائي">
                <flux:icon icon="play-circle" class="size-6" />
            </button>
            <button type="button" class="p-2 rounded-lg hover:bg-zinc-900/10 dark:hover:bg-white/10 transition"
                @click="goFullscreen()" title="ملء الشاشة (F)">
                <flux:icon icon="arrows-pointing-out" class="size-6" />
            </button>
            <button type="button" class="p-2 rounded-lg hover:bg-zinc-900/10 dark:hover:bg-white/10 transition"
                wire:click="$toggle('settingsOpen')" title="الإعدادات">
                <flux:icon icon="cog-6-tooth" class="size-6" />
            </button>
        </div>
    </div>

    {{-- Settings panel --}}
    @if($settingsOpen)
        <div class="absolute top-20 left-8 z-30 w-96 max-h-[75vh] overflow-y-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-5 shadow-2xl space-y-4">
            <flux:heading size="sm">إعدادات العرض</flux:heading>

            <div class="grid grid-cols-2 gap-3">
                <livewire:shared.hijri-datepicker wire:model.live="endDate" label="آخر يوم محسوب (هجري)" />
                <livewire:shared.hijri-datepicker wire:model.live="attendanceStart" label="بداية فترة الدوام (هجري)" />
            </div>

            <div>
                <div class="text-sm font-medium mb-2">القوائم المعروضة</div>
                <div class="space-y-1.5">
                    @foreach($allSlides as $slide)
                        <label class="flex items-center gap-2 text-sm cursor-pointer" wire:key="toggle-{{ $slide['key'] }}">
                            <input type="checkbox" class="rounded border-zinc-400 dark:border-zinc-600 bg-white dark:bg-zinc-800"
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
                    class="w-24 rounded-lg bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 px-3 py-1.5 text-sm">
            </div>
        </div>
    @endif

    {{-- Slides --}}
    <div class="grow relative px-8 pb-6 min-h-0">
        @forelse($slides as $index => $slide)
            @php $perPage = 10; @endphp
            <div x-show="current === {{ $index }}" x-cloak
                x-data="{
                    chunk: {{ $index === 0 ? 0 : -1 }},
                    per: {{ $perPage }},
                    rowsTotal: {{ count($slide['rows']) }},
                    get pages() { return Math.max(1, Math.ceil(this.rowsTotal / this.per)); },
                }"
                {{-- Each time this slide comes back into view, advance one chunk
                     so long lists show a different set of students per visit. --}}
                x-init="$watch('current', (val, old) => {
                    if (val === {{ $index }} && old !== {{ $index }}) chunk = (chunk + 1) % pages;
                })"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0"
                class="absolute inset-0 px-8 pb-6 flex flex-col">

                <div class="text-center shrink-0 mb-5">
                    <h1 class="text-4xl md:text-5xl font-extrabold">{{ $slide['title'] }}</h1>
                    <p class="text-zinc-500 dark:text-zinc-400 mt-2 text-lg">{{ $slide['subtitle'] }}</p>
                    <template x-if="pages > 1">
                        <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-1" x-text="`الصفحة ${chunk + 1} من ${pages} — تتبدل مع كل ظهور للقائمة`"></p>
                    </template>
                </div>

                <div class="grow min-h-0 max-w-5xl w-full mx-auto overflow-hidden">
                    @if(empty($slide['rows']))
                        <div class="h-full flex items-center justify-center text-2xl text-zinc-400 dark:text-zinc-600">
                            لا توجد نتائج في هذه الفترة
                        </div>
                    @else
                        <div class="grid gap-2.5">
                            @foreach($slide['rows'] as $rank => $row)
                                @php
                                    $medalColors = [
                                        ['disc' => '#f59e0b', 'edge' => '#b45309', 'text' => '#78350f'],
                                        ['disc' => '#d4d4d8', 'edge' => '#71717a', 'text' => '#3f3f46'],
                                        ['disc' => '#d97706', 'edge' => '#92400e', 'text' => '#451a03'],
                                    ][$rank] ?? null;
                                    $rowClass = match ($rank) {
                                        0 => 'bg-amber-100 border-amber-300 dark:bg-amber-500/15 dark:border-amber-400/40',
                                        1 => 'bg-zinc-200 border-zinc-300 dark:bg-zinc-400/15 dark:border-zinc-300/40',
                                        2 => 'bg-orange-100 border-orange-300 dark:bg-orange-700/15 dark:border-orange-500/40',
                                        default => 'bg-white border-zinc-200 dark:bg-white/5 dark:border-white/10',
                                    };
                                @endphp
                                <div x-show="Math.floor({{ $rank }} / per) === chunk"
                                    class="flex items-center gap-4 px-6 py-3 rounded-2xl border {{ $rowClass }}">
                                    <div class="w-12 flex items-center justify-center shrink-0">
                                        @if($medalColors)
                                            <svg viewBox="0 0 24 30" class="h-11 w-9" aria-hidden="true">
                                                <path d="M7 0h4l1 4.5L13 0h4l-3.2 11h-4.6z" fill="#dc2626" />
                                                <path d="M7 0h2.6l2.2 7.5L9.6 11H8.2z" fill="#b91c1c" />
                                                <circle cx="12" cy="20" r="8.2" fill="{{ $medalColors['disc'] }}" stroke="{{ $medalColors['edge'] }}" stroke-width="1.6" />
                                                <text x="12" y="24" text-anchor="middle" font-size="10.5" font-weight="800" fill="{{ $medalColors['text'] }}">{{ $rank + 1 }}</text>
                                            </svg>
                                        @else
                                            <span class="text-2xl font-black text-zinc-400 dark:text-zinc-500">{{ $rank + 1 }}</span>
                                        @endif
                                    </div>
                                    <div class="grow min-w-0">
                                        <div class="text-2xl font-bold truncate">{{ $row['name'] }}</div>
                                        @if($row['circle'] ?? null)
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $row['circle'] }}</div>
                                        @endif
                                    </div>
                                    @if($slide['type'] === 'attendance')
                                        <div class="flex items-center gap-6 shrink-0 text-center">
                                            <div>
                                                <div class="text-2xl font-extrabold text-red-600 dark:text-red-400 tabular-nums">{{ $row['absences'] }}</div>
                                                <div class="text-xs text-zinc-500">غياب</div>
                                            </div>
                                            <div>
                                                <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $row['presents'] }}</div>
                                                <div class="text-xs text-zinc-500">حضور</div>
                                            </div>
                                            <div>
                                                <div class="text-2xl font-extrabold tabular-nums">{{ $row['percentage'] }}%</div>
                                                <div class="text-xs text-zinc-500">انضباط</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="shrink-0 text-left">
                                            <span class="text-3xl font-extrabold tabular-nums text-amber-600 dark:text-amber-300">{{ number_format($row['value']) }}</span>
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400 mr-1">{{ $slide['unit'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="h-full flex items-center justify-center text-2xl text-zinc-400 dark:text-zinc-600">
                كل القوائم مخفية — فعّل قائمة واحدة على الأقل من الإعدادات
            </div>
        @endforelse

        {{-- Arrows --}}
        @if(count($slides) > 1)
            <button type="button" @click="next()"
                class="absolute left-3 top-1/2 -translate-y-1/2 z-20 p-3 rounded-full bg-zinc-900/10 hover:bg-zinc-900/20 dark:bg-white/10 dark:hover:bg-white/20 transition">
                <flux:icon icon="chevron-left" class="size-8" />
            </button>
            <button type="button" @click="prev()"
                class="absolute right-3 top-1/2 -translate-y-1/2 z-20 p-3 rounded-full bg-zinc-900/10 hover:bg-zinc-900/20 dark:bg-white/10 dark:hover:bg-white/20 transition">
                <flux:icon icon="chevron-right" class="size-8" />
            </button>
        @endif
    </div>
</div>
