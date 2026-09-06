<x-layouts.role-shell>
    <x-slot:title>
        {{ __('لوحة تحكم المعلم') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    @php
        // Read as a reach rather than as one teacher's cohorts: a supervisor who
        // holds this screen through his office teaches none, and asking him for
        // his circles would have been a call on nothing at all.
        $teacher = \App\Support\Scope::forRoute()->user();
        $teacherCircleIds = \App\Support\Scope::forRoute()
            ->applyToCircles(\App\Models\Circle::query())
            ->pluck('circles.id');

        $activeLeaderboard = null;

        if ($teacherCircleIds->isNotEmpty()) {
            $activeLeaderboard = \App\Models\Leaderboard::whereHas('circles', function($q) use ($teacherCircleIds) {
                    $q->whereIn('circles.id', $teacherCircleIds);
                })
                ->whereNotNull('supervisor_id')
                ->where('is_active_for_grading', true)
                ->first();

            if (!$activeLeaderboard) {
                $activeLeaderboard = \App\Models\Leaderboard::whereIn('circle_id', $teacherCircleIds)
                    ->whereNull('supervisor_id')
                    ->where('is_active_for_grading', true)
                    ->first();
            }
        }
    @endphp

    <div id="teacher-app-shell" x-data="{
        activeTab: '{{ $initialTab ?? 'dashboard' }}',
        showStaleWarning: false,
        staleTimer: null,

        armStaleTimer() {
            clearTimeout(this.staleTimer);
            this.staleTimer = setTimeout(() => {
                this.showStaleWarning = true;
            }, 20 * 60 * 1000);
        },

        registerActivity() {
            // Every interaction hits Livewire and returns fresh data, so activity
            // means the screen is NOT stale. Once the warning is visible it stays
            // until the teacher refreshes or dismisses it explicitly.
            if (this.showStaleWarning) return;
            this.armStaleTimer();
        },

        dismissStaleWarning() {
            this.showStaleWarning = false;
            this.armStaleTimer();
        },

        init() {
            // Listen for popstate (browser back/forward) to update tab if needed
            window.addEventListener('popstate', (e) => {
                const path = window.location.pathname;
                const match = path.match(/\/teacher\/([a-zA-Z0-9\-]+)/);
                if (match && match[1]) {
                    this.activeTab = match[1];
                } else if (path === '/teacher/dashboard') {
                    this.activeTab = 'dashboard';
                }
            });

            // Stale data warning: fires after 20 minutes of *inactivity*,
            // not 20 minutes after page load.
            this.armStaleTimer();
            window.addEventListener('pointerdown', () => this.registerActivity(), { passive: true });
            window.addEventListener('keydown', () => this.registerActivity(), { passive: true });
        }
    }"
    x-on:switch-tab.window="
        activeTab = $event.detail.tab;
        if ($event.detail.url && window.location.pathname !== $event.detail.url) {
            history.pushState(null, '', $event.detail.url);
        }
    "
    class="relative min-h-[80vh]">

        {{-- Stale Data Warning (shown after 20 minutes of inactivity) --}}
        <div x-cloak x-show="showStaleWarning"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed top-4 left-1/2 -translate-x-1/2 z-50 bg-amber-100 dark:bg-amber-900/90 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 px-4 py-2 rounded-full shadow-lg flex items-center gap-3">
             <flux:icon icon="clock" class="size-5" />
             <span class="text-sm font-medium">{{ __('مر وقت دون استخدام الصفحة وقد تكون البيانات غير محدثة') }}</span>
             <button onclick="window.location.reload()" class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1 rounded-full text-xs font-bold transition-colors">
                 {{ __('تحديث الآن') }} 🔄
             </button>
             <button x-on:click="dismissStaleWarning()" aria-label="{{ __('إغلاق') }}"
                 class="text-amber-600 dark:text-amber-300 hover:text-amber-900 dark:hover:text-amber-100 transition-colors">
                 <flux:icon icon="x-mark" class="size-4" />
             </button>
        </div>

        <div x-show="activeTab === 'students'" x-cloak>
            <livewire:teacher.student-manager />
        </div>

        <div x-show="activeTab === 'plan-creator'" x-cloak>
            <livewire:shared.plan-creator />
        </div>

        <div x-show="activeTab === 'tasmeeh'" x-cloak>
            <livewire:teacher.tasmeeh-manager />
        </div>

        <div x-show="activeTab === 'leaderboards'" x-cloak>
            <livewire:teacher.leaderboards />
        </div>

        <div x-show="activeTab === 'grade-items'" x-cloak class="p-1 md:p-8">
            @if ($activeLeaderboard)
                <livewire:teacher.leaderboard-grade :leaderboard-id="$activeLeaderboard->id" />
            @else
                <div class="text-center py-12 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                    <flux:icon icon="star" class="size-10 mx-auto text-zinc-400 mb-3" />
                    <flux:heading size="md" class="mb-2">{{ __('لا توجد مسابقة معتمدة للتسجيل') }}</flux:heading>
                    <p class="text-zinc-500 mb-4 max-w-md mx-auto">
                        {{ __('يرجى تحديد مسابقة من قائمة المسابقات لاعتمادها كالمسابقة الأساسية في شريط التنقل لتسجيل بنود التقييم.') }}
                    </p>
                    <flux:button x-on:click="$dispatch('switch-tab', { tab: 'leaderboards', url: '{{ route('teacher.leaderboards') }}' })" variant="primary" icon="trophy">
                        {{ __('الذهاب لإدارة المسابقات') }}
                    </flux:button>
                </div>
            @endif
        </div>

        <div x-show="activeTab === 'attendance'" x-cloak class="p-1 md:p-8">
            <livewire:teacher.attendance />
        </div>
    </div>
</x-layouts.role-shell>
