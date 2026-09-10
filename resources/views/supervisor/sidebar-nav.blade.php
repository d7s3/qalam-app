<flux:sidebar.item icon="home" :href="route('supervisor.dashboard')" :current="request()->routeIs('supervisor.dashboard')" wire:navigate>
    {{ __('الرئيسية') }}
</flux:sidebar.item>
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.student-log'))
    <flux:sidebar.item icon="book-open" :href="route('supervisor.student-log')" :current="request()->routeIs('supervisor.student-log')" wire:navigate>
        السجل التربوي
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.portal'))
    <flux:sidebar.item icon="megaphone" :href="route('supervisor.portal')" :current="request()->routeIs('supervisor.portal')" wire:navigate>
        بوابة الرسائل
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.motivations'))
    <flux:sidebar.item icon="sparkles" :href="route('supervisor.motivations')" :current="request()->routeIs('supervisor.motivations')" wire:navigate>
        مستودع الشواهد
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.self-program-tracks'))
    <flux:sidebar.item icon="adjustments-horizontal" :href="route('supervisor.self-program-tracks')" :current="request()->routeIs('supervisor.self-program-tracks')" wire:navigate>
        مجالات كتابة البرنامج الذاتي
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.period-values'))
    <flux:sidebar.item icon="sparkles" :href="route('supervisor.period-values')" :current="request()->routeIs('supervisor.period-values')" wire:navigate>
        قيم الفترة
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.event-visibility'))
    <flux:sidebar.item icon="eye" :href="route('supervisor.event-visibility')" :current="request()->routeIs('supervisor.event-visibility')" wire:navigate>
        رؤية الأحداث
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.my-day'))
    <flux:sidebar.item icon="sun" :href="route('supervisor.my-day')" :current="request()->routeIs('supervisor.my-day')" wire:navigate>
        {{ __('يومي') }}
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.task-board'))
    <flux:sidebar.item icon="rectangle-stack" :href="route('supervisor.task-board')" :current="request()->routeIs('supervisor.task-board')" wire:navigate>
        لوحة المهام
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.task-automation'))
    <flux:sidebar.item icon="arrow-path" :href="route('supervisor.task-automation')" :current="request()->routeIs('supervisor.task-automation')" wire:navigate>
        المهام التلقائية
    </flux:sidebar.item>
@endif
@php
    $supervisorUnreadMessages = \App\Services\MessagingService::unreadCountFor('supervisor', auth('supervisor')->id());
@endphp
@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.messages'))
    <flux:sidebar.item icon="envelope" :href="route('supervisor.messages')" :current="request()->routeIs('supervisor.messages')"
        :badge="$supervisorUnreadMessages > 0 ? $supervisorUnreadMessages : null" badge-color="rose" wire:navigate>
        {{ __('الرسائل') }}
    </flux:sidebar.item>
@endif

<flux:sidebar.group heading="العملية التعليمية" class="grid">
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.circles'))
        <flux:sidebar.item icon="circle-stack" :href="route('supervisor.circles')" :current="request()->routeIs('supervisor.circles')" wire:navigate>
            الدفعات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.students'))
        <flux:sidebar.item icon="academic-cap" :href="route('supervisor.students')" :current="request()->routeIs('supervisor.students')" wire:navigate>
            الطلاب
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.placement-requests'))
        <flux:sidebar.item icon="user-plus" :href="route('supervisor.placement-requests')" :current="request()->routeIs('supervisor.placement-requests')" wire:navigate>
            طلبات التسكين
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.self-program-weeks'))
        <flux:sidebar.item icon="squares-2x2" :href="route('supervisor.self-program-weeks')" :current="request()->routeIs('supervisor.self-program-weeks')" wire:navigate>
            {{ __('البرنامج الذاتي') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.self-program-progress'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('supervisor.self-program-progress')" :current="request()->routeIs('supervisor.self-program-progress')" wire:navigate>
            {{ __('تقدّم البرنامج') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.teachers'))
        <flux:sidebar.item icon="users" :href="route('supervisor.teachers')" :current="request()->routeIs('supervisor.teachers')" wire:navigate>
            المعلمون
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="المحتوى العلمي" class="grid">
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.odes'))
        <flux:sidebar.item icon="book-open" :href="route('supervisor.odes')" :current="request()->routeIs('supervisor.odes') && !request()->routeIs('supervisor.odes.plans') && !request()->routeIs('supervisor.odes.create-plan') && !request()->routeIs('supervisor.odes.paths')" wire:navigate>
            إدارة المنظومات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.odes.paths'))
        <flux:sidebar.item icon="map" :href="route('supervisor.odes.paths')" :current="request()->routeIs('supervisor.odes.paths*')" wire:navigate>
            مسارات حفظ المنظومات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.hadiths'))
        <flux:sidebar.item icon="document-text" :href="route('supervisor.hadiths')" :current="request()->routeIs('supervisor.hadiths') && !request()->routeIs('supervisor.hadiths.create-plan') && !request()->routeIs('supervisor.hadiths.paths')" wire:navigate>
            إدارة الأحاديث
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.hadiths.paths'))
        <flux:sidebar.item icon="map" :href="route('supervisor.hadiths.paths')" :current="request()->routeIs('supervisor.hadiths.paths*')" wire:navigate>
            مسارات حفظ المتون
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.odes.plans'))
        <flux:sidebar.item icon="clipboard-document-list" :href="route('supervisor.odes.plans')" :current="request()->routeIs('supervisor.odes.plans*') || request()->routeIs('supervisor.odes.create-plan*')" wire:navigate>
            خطط المنظومات المنشأة
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.competitions'))
    <flux:sidebar.group heading="التلعيب والمسابقات" class="grid">
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
    </flux:sidebar.group>
@endif

@if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.teacher-competitions'))
    <flux:sidebar.group heading="مسابقة المعلمين" class="grid">
        <flux:sidebar.item icon="trophy" :href="route('supervisor.teacher-competitions')" :current="request()->routeIs('supervisor.teacher-competitions*')" wire:navigate>
            مسابقة المعلمين
        </flux:sidebar.item>
    </flux:sidebar.group>
@endif

<flux:sidebar.group heading="المتابعة والتقارير" class="grid">
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.yearly-attendance'))
        <flux:sidebar.item icon="calendar" :href="route('supervisor.yearly-attendance')"
            :current="request()->routeIs('supervisor.yearly-attendance')" wire:navigate>
            متابعة تحضير الدفعات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.academic-calendar'))
        <flux:sidebar.item icon="calendar-days" :href="route('supervisor.academic-calendar')"
            :current="request()->routeIs('supervisor.academic-calendar')" wire:navigate>
            التقويم الأكاديمي
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.tasks'))
        <flux:sidebar.item icon="clipboard-document-list" :href="route('supervisor.tasks')"
            :current="request()->routeIs('supervisor.tasks')" wire:navigate>
            المهام
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.exceeded-limits'))
        <flux:sidebar.item icon="exclamation-triangle" :href="route('supervisor.exceeded-limits')"
            :current="request()->routeIs('supervisor.exceeded-limits')" wire:navigate>
            لائحة التجاوزات
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="إدارة النظام" class="grid">
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.whatsapp-settings'))
        <flux:sidebar.item icon="chat-bubble-left-right" :href="route('supervisor.whatsapp-settings')"
            :current="request()->routeIs('supervisor.whatsapp-settings')" wire:navigate>
            إعدادات الواتساب
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.forms'))
        <flux:sidebar.item icon="document-text" :href="route('supervisor.forms')"
            :current="request()->routeIs('supervisor.forms*')" wire:navigate>
            إدارة النماذج
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
    @if(\App\Support\RolePages::isEnabled('supervisor', 'supervisor.reports'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('supervisor.reports')" :current="request()->routeIs('supervisor.reports')" wire:navigate>
            {{ __('التقارير') }}
        </flux:sidebar.item>
    @endif

<x-held-screens-nav />
