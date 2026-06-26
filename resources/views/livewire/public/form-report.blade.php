<div class="w-full max-w-7xl mx-auto space-y-6 px-2 md:px-6" x-data="{ openDrawer: false }">
    <!-- Accent Color Top Bar -->
    <div class="h-2 w-full rounded-full" style="background-color: {{ $form->color }}"></div>

    <!-- Header Block -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 md:p-8 shadow-xs relative overflow-hidden">
        @if($form->header_image_path)
            <div class="absolute inset-0 bg-cover bg-center opacity-10" style="background-image: url('{{ asset('storage/' . $form->header_image_path) }}')"></div>
        @endif
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-zinc-900 dark:text-white">{{ $form->title }}</h1>
                <p class="text-sm md:text-base text-zinc-500 dark:text-zinc-400 mt-2">{{ $form->description ?: 'تقرير استعراض وتحليل البيانات المستلمة.' }}</p>
            </div>
            
            <div class="flex flex-wrap gap-2 text-xs">
                @if(!empty($groupBy))
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full font-medium bg-indigo-50 dark:bg-indigo-950/20 text-indigo-700 dark:text-indigo-400 border border-indigo-150 dark:border-indigo-850">
                        تجميع رئيسي: 
                        {{ collect($form->fields)->firstWhere('id', $groupBy)['label'] ?? '' }}
                    </span>
                @endif
                @if(!empty($subGroupBy))
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full font-medium bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400 border border-emerald-150 dark:border-emerald-850">
                        تجميع فرعي: 
                        {{ collect($form->fields)->firstWhere('id', $subGroupBy)['label'] ?? '' }}
                    </span>
                @endif
                @php
                    $activeFiltersCount = collect($filters)->filter(fn($val) => !empty($val))->count();
                @endphp
                @if($activeFiltersCount > 0)
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full font-medium bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 border border-amber-150 dark:border-amber-850">
                        الفلاتر النشطة: {{ $activeFiltersCount }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Active Fields for Columns (Excluding grouped fields) -->
    @php
        $activeFields = collect($form->fields)->reject(fn($f) => in_array($f['id'], [$groupBy, $subGroupBy]))->toArray();
    @endphp

    <!-- Responses Data Table -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
        
        @if($hasGrouping)
            <!-- Grouped Layout -->
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($groupedData as $primaryKey => $group)
                    <div class="p-4 md:p-6 space-y-4">
                        <!-- Primary Group Header -->
                        <div class="flex items-center gap-2 border-b border-zinc-150 dark:border-zinc-800 pb-3">
                            <span class="w-3 h-6 rounded-xs" style="background-color: {{ $form->color }}"></span>
                            <h2 class="text-lg font-bold text-zinc-800 dark:text-zinc-100">
                                {{ collect($form->fields)->firstWhere('id', $groupBy)['label'] ?? '' }}: 
                                <span class="text-zinc-900 dark:text-white underline decoration-2" style="text-decoration-color: {{ $form->color }}">{{ $primaryKey }}</span>
                            </h2>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-500 font-medium">
                                {{ $hasSubGrouping ? collect($group)->flatten(1)->count() : $group->count() }} ردود
                            </span>
                        </div>

                        <!-- Nested Secondary Grouping -->
                        @if($hasSubGrouping)
                            <div class="space-y-6 ps-4 border-r-2 border-zinc-100 dark:border-zinc-800">
                                @foreach($group as $secondaryKey => $subResponses)
                                    <div class="space-y-3">
                                        <!-- Secondary Group Header -->
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-4 rounded-xs bg-emerald-500"></span>
                                            <h3 class="text-sm font-bold text-zinc-700 dark:text-zinc-300">
                                                {{ collect($form->fields)->firstWhere('id', $subGroupBy)['label'] ?? '' }}: 
                                                <span class="text-zinc-900 dark:text-white font-extrabold">{{ $secondaryKey }}</span>
                                            </h3>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 font-semibold">
                                                {{ $subResponses->count() }} ردود
                                            </span>
                                        </div>

                                        <!-- Nested Table -->
                                        @include('livewire.public.partials.responses-table', ['responses' => $subResponses, 'activeFields' => $activeFields])
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <!-- Single Level Table -->
                            @include('livewire.public.partials.responses-table', ['responses' => $group, 'activeFields' => $activeFields])
                        @endif
                    </div>
                @empty
                    <div class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                        لا توجد نتائج مطابقة للتصفية الحالية.
                    </div>
                @endforelse
            </div>
        @else
            <!-- Flat Layout (No Grouping) -->
            @if($groupedData->isEmpty())
                <div class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                    لا توجد نتائج مطابقة للتصفية الحالية أو لم يتم تقديم ردود بعد.
                </div>
            @else
                @include('livewire.public.partials.responses-table', ['responses' => $groupedData, 'activeFields' => $activeFields])
            @endif
        @endif

    </div>

    <!-- Floating Action Button (FAB) -->
    <button @click="openDrawer = true" class="fixed bottom-6 right-6 w-14 h-14 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-110 active:scale-95 transition-all cursor-pointer z-40 focus:outline-hidden" style="background-color: {{ $form->color }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
        </svg>
    </button>

    <!-- Slide-over Drawer Backdrop & Panel -->
    <div x-cloak x-show="openDrawer" class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div x-show="openDrawer" 
             x-transition:enter="ease-in-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="openDrawer = false"
             class="fixed inset-0 bg-zinc-900/40 backdrop-blur-xs transition-opacity"></div>

        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-0">
                    <!-- Drawer Panel -->
                    <div x-show="openDrawer" 
                         x-transition:enter="transform transition ease-in-out duration-300 sm:duration-300"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-300 sm:duration-300"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full"
                         class="pointer-events-auto w-screen max-w-md">
                        <div class="flex h-full flex-col overflow-y-scroll bg-white dark:bg-zinc-950 py-6 shadow-2xl border-l border-zinc-200 dark:border-zinc-800">
                            <!-- Drawer Header -->
                            <div class="px-4 sm:px-6 border-b border-zinc-150 dark:border-zinc-850 pb-4 flex items-center justify-between">
                                <h2 class="text-lg font-bold text-zinc-900 dark:text-white" id="slide-over-title">خيارات العرض والتصفية</h2>
                                <button type="button" @click="openDrawer = false" class="relative rounded-md text-zinc-400 hover:text-zinc-500 focus:outline-hidden">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Drawer Body -->
                            <div class="relative mt-6 flex-1 px-4 sm:px-6 space-y-6">
                                <!-- Reset Filters -->
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-zinc-400">تحكم بالبيانات المعروضة وتصفيتها علناً</span>
                                    <button wire:click="resetFilters" class="text-xs font-bold text-rose-500 hover:text-rose-600 transition-colors flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        إعادة تعيين الكل
                                    </button>
                                </div>

                                <!-- Accordion 1: Grouping (تجميع البيانات) -->
                                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden" x-data="{ open: true }">
                                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 bg-zinc-50 dark:bg-zinc-900/50 hover:bg-zinc-100/50 transition-colors">
                                        <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-zinc-500">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                            </svg>
                                            تجميع البيانات
                                        </span>
                                        <svg class="h-5 w-5 text-zinc-500 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    
                                    <div x-show="open" x-collapse class="p-4 bg-white dark:bg-zinc-950 space-y-4 border-t border-zinc-200 dark:border-zinc-800">
                                        <!-- Primary Grouping -->
                                        <div class="space-y-1.5">
                                            <label class="text-xs font-semibold text-zinc-500">التجميع الرئيسي (حسب الحقل)</label>
                                            <select wire:model.live="groupBy" class="block w-full rounded-lg border border-zinc-250 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200">
                                                <option value="">-- بلا تجميع رئيسي --</option>
                                                @foreach($form->fields as $field)
                                                    <option value="{{ $field['id'] }}">{{ $field['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Secondary Grouping -->
                                        @if(!empty($groupBy))
                                            <div class="space-y-1.5" x-transition>
                                                <label class="text-xs font-semibold text-zinc-500">التجميع الفرعي (داخل المجموعات الرئيسية)</label>
                                                <select wire:model.live="subGroupBy" class="block w-full rounded-lg border border-zinc-250 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200">
                                                    <option value="">-- بلا تجميع فرعي --</option>
                                                    @foreach($form->fields as $field)
                                                        @if($field['id'] !== $groupBy)
                                                            <option value="{{ $field['id'] }}">{{ $field['label'] }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Accordion 2: Filters (الفلاتر والتصفية) -->
                                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden" x-data="{ open: true }">
                                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 bg-zinc-50 dark:bg-zinc-900/50 hover:bg-zinc-100/50 transition-colors">
                                        <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-zinc-500">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                                            </svg>
                                            تصفية البيانات (الفلاتر)
                                        </span>
                                        <svg class="h-5 w-5 text-zinc-500 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    
                                    <div x-show="open" x-collapse class="p-4 bg-white dark:bg-zinc-950 space-y-4 border-t border-zinc-200 dark:border-zinc-800 divide-y divide-zinc-150 dark:divide-zinc-850">
                                        @foreach($form->fields as $field)
                                            <div class="pt-3 first:pt-0 space-y-2">
                                                <label class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $field['label'] }}</label>
                                                
                                                @if($field['type'] === 'text')
                                                    <!-- Text Input Filter -->
                                                    <input type="text" wire:model.live.debounce.300ms="filters.{{ $field['id'] }}" placeholder="ابحث في الإجابات..." class="block w-full rounded-lg border border-zinc-250 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200" />
                                                
                                                @elseif(in_array($field['type'], ['select', 'multiselect']))
                                                    <!-- Checkboxes Filter for options -->
                                                    <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                                                        @php
                                                            $optionsList = $field['options'] ?? [];
                                                            if ($field['allow_other'] ?? false) {
                                                                // add standard placeholder or let answers flow
                                                            }
                                                        @endphp
                                                        @foreach($optionsList as $opt)
                                                            <label class="flex items-center gap-2 cursor-pointer text-xs py-1">
                                                                <input type="checkbox" wire:model.live="filters.{{ $field['id'] }}" value="{{ $opt }}" class="rounded border-zinc-300 text-accent focus:ring-accent" />
                                                                <span class="text-zinc-700 dark:text-zinc-300">{{ $opt }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>

                                                @elseif($field['type'] === 'date')
                                                    <!-- Date text or generic input filter -->
                                                    <input type="text" wire:model.live.debounce.300ms="filters.{{ $field['id'] }}" placeholder="مثال: YYYY-MM-DD..." class="block w-full rounded-lg border border-zinc-250 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200" />
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
