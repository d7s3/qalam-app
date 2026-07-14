@if(auth('student')->check())
    @php
        $student = auth('student')->user();
        $activeGamification = \App\Models\Leaderboard::whereHas('circles', fn($q) => $q->where('circles.id', $student->circle_id))
            ->whereNotNull('supervisor_id')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$activeGamification) {
            $activeGamification = \App\Models\Leaderboard::where('circle_id', $student->circle_id)
                ->whereNull('supervisor_id')
                ->where('is_active', true)
                ->latest()
                ->first();
        }

        if ($activeGamification && $activeGamification->competition_type !== 'gamification') {
            $activeGamification = null;
        }
    @endphp

    @if($activeGamification)
        @php
            $studentTeam = \App\Models\GamificationTeam::whereHas('students', fn($q) => $q->where('users.id', $student->id))
                ->where('leaderboard_id', $activeGamification->id)
                ->first();
            $theme = \App\Services\GamificationThemeService::getTheme($activeGamification);
            $navColor = $studentTeam ? ($studentTeam->color ?? ($theme['color'] ?? '#4f46e5')) : ($theme['color'] ?? '#4f46e5');
            $teamName = $theme['team_possessive_my'] ?? 'فريقي';

            $navItems = [
                ['tab' => 'leaderboard', 'name' => 'الرئيسية',  'icon' => 'home'],
                ['tab' => 'store',       'name' => 'المتجر',     'icon' => 'shopping-bag'],
                ['tab' => 'badges',      'name' => 'الأوسمة',    'icon' => 'trophy'],
                ['tab' => 'news',        'name' => 'الأخبار',    'icon' => 'newspaper'],
            ];

            if ($studentTeam) {
                $navItems[] = ['tab' => 'team', 'name' => $teamName, 'icon' => 'users'];
            }
        @endphp

        <div x-data="{ activeTab: localStorage.getItem('student-gam-tab') || 'leaderboard' }"
            x-on:gamnav-changed.window="activeTab = $event.detail.tab; localStorage.setItem('student-gam-tab', $event.detail.tab)"
            class="fixed bottom-0 w-full start-0 z-[9999] lg:hidden border-t border-white/10 shadow-none"
            style="background-color: {{ $navColor }}; padding-bottom: env(safe-area-inset-bottom, 12px);">
            <div class="flex items-center justify-around px-2 min-h-18 max-w-lg mx-auto">

                @foreach($navItems as $item)
                    <button
                        x-on:click="window.dispatchEvent(new CustomEvent('gamnav-changed', { detail: { tab: '{{ $item['tab'] }}' } })); @if($item['tab'] === 'news') window.dispatchEvent(new CustomEvent('news-opened')); @endif window.scrollTo({ top: 0, behavior: 'instant' });"
                        :class="activeTab === '{{ $item['tab'] }}' ? 'text-white' : 'text-white/60 hover:text-white'"
                        class="relative flex flex-col items-center justify-center duration-300 ease-out h-full flex-1 py-2 cursor-pointer">

                        <div :class="activeTab === '{{ $item['tab'] }}' ? 'bg-white/15 px-6 py-2' : 'p-2'"
                            class="relative flex items-center justify-center min-h-15 rounded-full duration-300">
                            <flux:icon icon="{{ $item['icon'] }}" class="size-7 shrink-0"
                                x-bind:variant="activeTab === '{{ $item['tab'] }}' ? 'solid' : 'outline'" />
                            <span x-show="activeTab === '{{ $item['tab'] }}'" x-cloak class="ms-2 font-bold text-sm truncate block">{{ $item['name'] }}</span>

                            @if($item['tab'] === 'news')
                                <livewire:student.gamification-news-badge :leaderboard-id="$activeGamification->id" wire:key="gam-news-badge-{{ $activeGamification->id }}" />
                            @endif
                        </div>
                    </button>
                @endforeach

            </div>
        </div>
    @endif
@endif