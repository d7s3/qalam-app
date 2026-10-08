<div class="fixed bottom-0 left-0 rounded-full right-0 z-bar lg:hidden bg-maroon dark:bg-accent-dark border-t border-white/10 shadow-none"
    style="padding-bottom: env(safe-area-inset-bottom, 12px);">
    <div class="flex items-center justify-around px-2 min-h-18 max-w-lg mx-auto ">

        @php
            $navItems = [
                ['name' => 'التحضير', 'route' => 'teacher.attendance', 'icon' => 'calendar', 'tab' => 'attendance'],
                ['name' => 'التسميع', 'route' => 'teacher.tasmeeh', 'icon' => 'book-open', 'tab' => 'tasmeeh'],
                ['name' => 'البنود', 'route' => 'teacher.grade-items', 'icon' => 'star', 'tab' => 'grade-items'],
                ['name' => 'الخطط', 'route' => 'teacher.plan-creator', 'icon' => 'pencil-square', 'tab' => 'plan-creator'],
                ['name' => 'الطلاب', 'route' => 'teacher.students', 'icon' => 'users', 'tab' => 'students'],
            ];
        @endphp

        @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}" 
                x-data="{ isActive: '{{ $initialTab ?? '' }}' === '{{ $item['tab'] }}' || {{ request()->routeIs($item['route'] . '*') ? 'true' : 'false' }} }"
                x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: '{{ $item['tab'] }}', url: '{{ route($item['route']) }}' }); } else { Livewire.navigate('{{ route($item['route']) }}'); }"
                x-on:switch-tab.window="isActive = ($event.detail.tab === '{{ $item['tab'] }}')"
                :class="isActive ? 'text-white' : 'text-white/60 hover:text-white'"
                class="relative flex flex-col items-center justify-center duration-300 ease-out h-full flex-1">

                <div :class="isActive ? 'bg-white/15 px-6 py-2' : 'p-2'"
                    class="relative flex items-center justify-center min-h-15 rounded-full duration-300">
                    <flux:icon icon="{{ $item['icon'] }}" class="size-7 shrink-0"
                        x-bind:variant="isActive ? 'solid' : 'outline'" />
                    <span x-show="isActive" x-cloak class="ms-2 font-bold text-sm truncate block">{{ $item['name'] }}</span>
                </div>
            </a>
        @endforeach
    </div>
</div>