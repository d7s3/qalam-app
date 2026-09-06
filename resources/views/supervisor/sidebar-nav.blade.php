<flux:sidebar.item class="[&_svg]:bg-[#3b82f6] hover:[&_svg]:bg-[#2563eb]" icon="home" :href="route('supervisor.dashboard')" :current="request()->routeIs('supervisor.dashboard')" wire:navigate>
    {{ __('الرئيسية') }}
</flux:sidebar.item>
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.student-log'))
    <flux:sidebar.item class="[&_svg]:bg-[#8b5cf6]" icon="book-open" :href="route('supervisor.student-log')" :current="request()->routeIs('supervisor.student-log')" wire:navigate>
        السجل التربوي
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.portal'))
    <flux:sidebar.item class="[&_svg]:bg-[#e11d48]" icon="megaphone" :href="route('supervisor.portal')" :current="request()->routeIs('supervisor.portal')" wire:navigate>
        بوابة الرسائل
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.motivations'))
    <flux:sidebar.item class="[&_svg]:bg-[#f59e0b]" icon="sparkles" :href="route('supervisor.motivations')" :current="request()->routeIs('supervisor.motivations')" wire:navigate>
        مستودع الشواهد
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.self-program-tracks'))
    <flux:sidebar.item class="[&_svg]:bg-[#14b8a6] hover:[&_svg]:bg-[#0d9488]" icon="adjustments-horizontal" :href="route('supervisor.self-program-tracks')" :current="request()->routeIs('supervisor.self-program-tracks')" wire:navigate>
        مجالات البرنامج الذاتي
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.period-values'))
    <flux:sidebar.item class="[&_svg]:bg-[#d946ef] hover:[&_svg]:bg-[#c026d3]" icon="sparkles" :href="route('supervisor.period-values')" :current="request()->routeIs('supervisor.period-values')" wire:navigate>
        قيم الفترة
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.event-visibility'))
    <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="eye" :href="route('supervisor.event-visibility')" :current="request()->routeIs('supervisor.event-visibility')" wire:navigate>
        رؤية الأحداث
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.my-day'))
    <flux:sidebar.item class="[&_svg]:bg-[#f59e0b] hover:[&_svg]:bg-[#d97706]" icon="sun" :href="route('supervisor.my-day')" :current="request()->routeIs('supervisor.my-day')" wire:navigate>
        {{ __('يومي') }}
    </flux:sidebar.item>
@endif
@php
    $supervisorUnreadMessages = \App\Services\MessagingService::unreadCountFor('supervisor', auth('supervisor')->id());
@endphp
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.messages'))
    <flux:sidebar.item class="[&_svg]:bg-[#e11d48] hover:[&_svg]:bg-[#be123c]" icon="envelope" :href="route('supervisor.messages')" :current="request()->routeIs('supervisor.messages')"
        :badge="$supervisorUnreadMessages > 0 ? $supervisorUnreadMessages : null" badge-color="rose" wire:navigate>
        {{ __('الرسائل') }}
    </flux:sidebar.item>
@endif

<flux:sidebar.group heading="العملية التعليمية" class="grid">
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.circles'))
        <flux:sidebar.item class="[&_svg]:bg-[#0d9488] hover:[&_svg]:bg-[#0f766e]" icon="circle-stack" :href="route('supervisor.circles')" :current="request()->routeIs('supervisor.circles')" wire:navigate>
            الدفعات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.students'))
        <flux:sidebar.item class="[&_svg]:bg-[#a855f7] hover:[&_svg]:bg-[#9333ea]" icon="academic-cap" :href="route('supervisor.students')" :current="request()->routeIs('supervisor.students')" wire:navigate>
            الطلاب
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.placement-requests'))
        <flux:sidebar.item class="[&_svg]:bg-[#f59e0b] hover:[&_svg]:bg-[#d97706]" icon="user-plus" :href="route('supervisor.placement-requests')" :current="request()->routeIs('supervisor.placement-requests')" wire:navigate>
            طلبات التسكين
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.self-program'))
        <flux:sidebar.item class="[&_svg]:bg-[#10b981] hover:[&_svg]:bg-[#059669]" icon="squares-2x2" :href="route('supervisor.self-program')" :current="request()->routeIs('supervisor.self-program')" wire:navigate>
            {{ __('البرنامج الذاتي') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.self-program-progress'))
        <flux:sidebar.item class="[&_svg]:bg-[#10b981] hover:[&_svg]:bg-[#059669]" icon="chart-bar-square" :href="route('supervisor.self-program-progress')" :current="request()->routeIs('supervisor.self-program-progress')" wire:navigate>
            {{ __('تقدّم البرنامج') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.teachers'))
        <flux:sidebar.item class="[&_svg]:bg-[#6366f1] hover:[&_svg]:bg-[#4f46e5]" icon="users" :href="route('supervisor.teachers')" :current="request()->routeIs('supervisor.teachers')" wire:navigate>
            المعلمون
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="المحتوى العلمي" class="grid">
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.odes'))
        <flux:sidebar.item class="[&_svg]:bg-[#f97316] hover:[&_svg]:bg-[#ea580c]" icon="book-open" :href="route('supervisor.odes')" :current="request()->routeIs('supervisor.odes') && !request()->routeIs('supervisor.odes.plans') && !request()->routeIs('supervisor.odes.create-plan') && !request()->routeIs('supervisor.odes.paths')" wire:navigate>
            إدارة المنظومات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.odes.paths'))
        <flux:sidebar.item class="[&_svg]:bg-[#8b5cf6] hover:[&_svg]:bg-[#7c3aed]" icon="map" :href="route('supervisor.odes.paths')" :current="request()->routeIs('supervisor.odes.paths*')" wire:navigate>
            مسارات حفظ المنظومات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.hadiths'))
        <flux:sidebar.item class="[&_svg]:bg-[#f43f5e] hover:[&_svg]:bg-[#e11d48]" icon="document-text" :href="route('supervisor.hadiths')" :current="request()->routeIs('supervisor.hadiths') && !request()->routeIs('supervisor.hadiths.create-plan') && !request()->routeIs('supervisor.hadiths.paths')" wire:navigate>
            إدارة الأحاديث
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.hadiths.paths'))
        <flux:sidebar.item class="[&_svg]:bg-[#14b8a6] hover:[&_svg]:bg-[#0d9488]" icon="map" :href="route('supervisor.hadiths.paths')" :current="request()->routeIs('supervisor.hadiths.paths*')" wire:navigate>
            مسارات حفظ المتون
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.odes.plans'))
        <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="clipboard-document-list" :href="route('supervisor.odes.plans')" :current="request()->routeIs('supervisor.odes.plans*') || request()->routeIs('supervisor.odes.create-plan*')" wire:navigate>
            خطط المنظومات المنشأة
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.competitions'))
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
@endif

@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.teacher-competitions'))
    <flux:sidebar.group heading="مسابقة المعلمين" class="grid">
        <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="trophy" :href="route('supervisor.teacher-competitions')" :current="request()->routeIs('supervisor.teacher-competitions*')" wire:navigate>
            مسابقة المعلمين
        </flux:sidebar.item>
    </flux:sidebar.group>
@endif

<flux:sidebar.group heading="المتابعة والتقارير" class="grid">
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.yearly-attendance'))
        <flux:sidebar.item class="[&_svg]:bg-[#10b981] hover:[&_svg]:bg-[#059669]" icon="calendar" :href="route('supervisor.yearly-attendance')"
            :current="request()->routeIs('supervisor.yearly-attendance')" wire:navigate>
            متابعة تحضير الدفعات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.academic-calendar'))
        <flux:sidebar.item class="[&_svg]:bg-[#f59e0b] hover:[&_svg]:bg-[#d97706]" icon="calendar-days" :href="route('supervisor.academic-calendar')"
            :current="request()->routeIs('supervisor.academic-calendar')" wire:navigate>
            التقويم الأكاديمي
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.tasks'))
        <flux:sidebar.item class="[&_svg]:bg-[#64748b] hover:[&_svg]:bg-[#475569]" icon="clipboard-document-list" :href="route('supervisor.tasks')"
            :current="request()->routeIs('supervisor.tasks')" wire:navigate>
            المهام
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.exceeded-limits'))
        <flux:sidebar.item class="[&_svg]:bg-[#ef4444] hover:[&_svg]:bg-[#dc2626]" icon="exclamation-triangle" :href="route('supervisor.exceeded-limits')"
            :current="request()->routeIs('supervisor.exceeded-limits')" wire:navigate>
            لائحة التجاوزات
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="إدارة النظام" class="grid">
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.whatsapp-settings'))
        <flux:sidebar.item class="[&_svg]:bg-[#22c55e] hover:[&_svg]:bg-[#16a34a]" icon="chat-bubble-left-right" :href="route('supervisor.whatsapp-settings')"
            :current="request()->routeIs('supervisor.whatsapp-settings')" wire:navigate>
            إعدادات الواتساب
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.forms'))
        <flux:sidebar.item class="[&_svg]:bg-[#14b8a6] hover:[&_svg]:bg-[#0d9488]" icon="document-text" :href="route('supervisor.forms')"
            :current="request()->routeIs('supervisor.forms*')" wire:navigate>
            إدارة النماذج
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.reports'))
        <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="chart-bar-square" :href="route('supervisor.reports')" :current="request()->routeIs('supervisor.reports')" wire:navigate>
            {{ __('التقارير') }}
        </flux:sidebar.item>
    @endif

<x-held-screens-nav />
