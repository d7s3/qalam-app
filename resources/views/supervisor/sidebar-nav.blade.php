<flux:sidebar.group :heading="__('Platform')" class="grid">
    <flux:sidebar.item icon="home" :href="route('supervisor.dashboard')" :current="request()->routeIs('supervisor.dashboard')" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>
</flux:sidebar.group>

<flux:sidebar.group heading="الإشراف" class="grid">
    <flux:sidebar.item icon="circle-stack" :href="route('supervisor.circles')" :current="request()->routeIs('supervisor.circles')" wire:navigate>
        الحلقات
    </flux:sidebar.item>
    <flux:sidebar.item icon="academic-cap" :href="route('supervisor.students')" :current="request()->routeIs('supervisor.students')" wire:navigate>
        الطلاب
    </flux:sidebar.item>
    <flux:sidebar.item icon="users" :href="route('supervisor.teachers')" :current="request()->routeIs('supervisor.teachers')" wire:navigate>
        المعلمون
    </flux:sidebar.item>
    <flux:sidebar.item icon="book-open" :href="route('supervisor.odes')" :current="request()->routeIs('supervisor.odes') && !request()->routeIs('supervisor.odes.plans')" wire:navigate>
        إدارة المنظومات
    </flux:sidebar.item>
    <flux:sidebar.item icon="clipboard-document-list" :href="route('supervisor.odes.plans')" :current="request()->routeIs('supervisor.odes.plans*')" wire:navigate>
        خطط المنظومات المنشأة
    </flux:sidebar.item>
    <flux:sidebar.item icon="calendar" :href="route('supervisor.yearly-attendance')"
        :current="request()->routeIs('supervisor.yearly-attendance')" wire:navigate>
        متابعة الحلقات السنوي
    </flux:sidebar.item>
    <flux:sidebar.item icon="calendar" :href="route('supervisor.academic-calendar')"
        :current="request()->routeIs('supervisor.academic-calendar')" wire:navigate>
        التقويم الأكاديمي
    </flux:sidebar.item>
    <flux:sidebar.item icon="trophy" :href="route('supervisor.competitions')" :current="request()->routeIs('supervisor.competitions') && request()->query('create_gamification') !== '1'" wire:navigate>
        المسابقات
    </flux:sidebar.item>
    <flux:sidebar.item icon="plus-circle" :href="route('supervisor.competitions', ['create_gamification' => 1])" :current="request()->routeIs('supervisor.competitions') && request()->query('create_gamification') === '1'" wire:navigate>
        إنشاء مسابقة تلعيب
    </flux:sidebar.item>
    @php
        $latestGamification = \App\Models\Leaderboard::where('competition_type', 'gamification')->latest()->first();
    @endphp
    @if($latestGamification)
        <flux:sidebar.item icon="sparkles" :href="route('supervisor.competitions.gamification', $latestGamification->id)" :current="request()->routeIs('supervisor.competitions.gamification')" wire:navigate>
            إدارة التلعيب
        </flux:sidebar.item>
    @endif
    <flux:sidebar.item icon="clipboard-document-list" :href="route('supervisor.tasks')"
        :current="request()->routeIs('supervisor.tasks')" wire:navigate>
        المهام
    </flux:sidebar.item>
    <flux:sidebar.item icon="exclamation-triangle" :href="route('supervisor.exceeded-limits')"
        :current="request()->routeIs('supervisor.exceeded-limits')" wire:navigate>
        لائحة التجاوزات
    </flux:sidebar.item>
    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('supervisor.whatsapp-settings')"
        :current="request()->routeIs('supervisor.whatsapp-settings')" wire:navigate>
        إعدادات الواتساب
    </flux:sidebar.item>
</flux:sidebar.group>
