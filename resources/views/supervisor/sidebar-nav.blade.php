<flux:sidebar.item class="[&_svg]:bg-[#3b82f6] hover:[&_svg]:bg-[#2563eb]" icon="home" :href="route('supervisor.dashboard')" :current="request()->routeIs('supervisor.dashboard')" wire:navigate>
    {{ __('Dashboard') }}
</flux:sidebar.item>

<flux:sidebar.group heading="العملية التعليمية" class="grid">
    <flux:sidebar.item class="[&_svg]:bg-[#0d9488] hover:[&_svg]:bg-[#0f766e]" icon="circle-stack" :href="route('supervisor.circles')" :current="request()->routeIs('supervisor.circles')" wire:navigate>
        الحلقات
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#a855f7] hover:[&_svg]:bg-[#9333ea]" icon="academic-cap" :href="route('supervisor.students')" :current="request()->routeIs('supervisor.students')" wire:navigate>
        الطلاب
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#6366f1] hover:[&_svg]:bg-[#4f46e5]" icon="users" :href="route('supervisor.teachers')" :current="request()->routeIs('supervisor.teachers')" wire:navigate>
        المعلمون
    </flux:sidebar.item>
</flux:sidebar.group>

<flux:sidebar.group heading="المحتوى العلمي" class="grid">
    <flux:sidebar.item class="[&_svg]:bg-[#f97316] hover:[&_svg]:bg-[#ea580c]" icon="book-open" :href="route('supervisor.odes')" :current="request()->routeIs('supervisor.odes') && !request()->routeIs('supervisor.odes.plans') && !request()->routeIs('supervisor.odes.create-plan')" wire:navigate>
        إدارة المنظومات
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#f43f5e] hover:[&_svg]:bg-[#e11d48]" icon="document-text" :href="route('supervisor.hadiths')" :current="request()->routeIs('supervisor.hadiths') && !request()->routeIs('supervisor.hadiths.plans') && !request()->routeIs('supervisor.hadiths.create-plan')" wire:navigate>
        إدارة الأحاديث
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="clipboard-document-list" :href="route('supervisor.odes.plans')" :current="request()->routeIs('supervisor.odes.plans*') || request()->routeIs('supervisor.odes.create-plan*')" wire:navigate>
        خطط المنظومات المنشأة
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#e11d48] hover:[&_svg]:bg-[#be123c]" icon="clipboard-document-list" :href="route('supervisor.hadiths.plans')" :current="request()->routeIs('supervisor.hadiths.plans*') || request()->routeIs('supervisor.hadiths.create-plan*')" wire:navigate>
        خطط الأحاديث المنشأة
    </flux:sidebar.item>
</flux:sidebar.group>

<flux:sidebar.group heading="التلعيب والمسابقات" class="grid">
    <flux:sidebar.item class="[&_svg]:bg-[#eab308] hover:[&_svg]:bg-[#ca8a04]" icon="trophy" :href="route('supervisor.competitions')" :current="request()->routeIs('supervisor.competitions') && request()->query('create_gamification') !== '1'" wire:navigate>
        المسابقات
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#ec4899] hover:[&_svg]:bg-[#db2777]" icon="plus-circle" :href="route('supervisor.competitions', ['create_gamification' => 1])" :current="request()->routeIs('supervisor.competitions') && request()->query('create_gamification') === '1'" wire:navigate>
        إنشاء مسابقة تلعيب
    </flux:sidebar.item>
    @php
        $latestGamification = \App\Models\Leaderboard::where('competition_type', 'gamification')->latest()->first();
    @endphp
    @if($latestGamification)
        <flux:sidebar.item class="[&_svg]:bg-[#d946ef] hover:[&_svg]:bg-[#c026d3]" icon="sparkles" :href="route('supervisor.competitions.gamification', $latestGamification->id)" :current="request()->routeIs('supervisor.competitions.gamification')" wire:navigate>
            إدارة التلعيب
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="المتابعة والتقارير" class="grid">
    <flux:sidebar.item class="[&_svg]:bg-[#10b981] hover:[&_svg]:bg-[#059669]" icon="calendar" :href="route('supervisor.yearly-attendance')"
        :current="request()->routeIs('supervisor.yearly-attendance')" wire:navigate>
        متابعة الحلقات السنوي
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#f59e0b] hover:[&_svg]:bg-[#d97706]" icon="calendar-days" :href="route('supervisor.academic-calendar')"
        :current="request()->routeIs('supervisor.academic-calendar')" wire:navigate>
        التقويم الأكاديمي
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#64748b] hover:[&_svg]:bg-[#475569]" icon="clipboard-document-list" :href="route('supervisor.tasks')"
        :current="request()->routeIs('supervisor.tasks')" wire:navigate>
        المهام
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#ef4444] hover:[&_svg]:bg-[#dc2626]" icon="exclamation-triangle" :href="route('supervisor.exceeded-limits')"
        :current="request()->routeIs('supervisor.exceeded-limits')" wire:navigate>
        لائحة التجاوزات
    </flux:sidebar.item>
</flux:sidebar.group>

<flux:sidebar.group heading="إدارة النظام" class="grid">
    <flux:sidebar.item class="[&_svg]:bg-[#22c55e] hover:[&_svg]:bg-[#16a34a]" icon="chat-bubble-left-right" :href="route('supervisor.whatsapp-settings')"
        :current="request()->routeIs('supervisor.whatsapp-settings')" wire:navigate>
        إعدادات الواتساب
    </flux:sidebar.item>
</flux:sidebar.group>
