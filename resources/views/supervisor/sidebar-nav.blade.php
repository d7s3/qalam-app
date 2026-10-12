{{--
    The supervisor's sidebar: the pages he opens every day first, the rest
    folded into groups that open on their own when he is on one of them.

    It ran to some thirty-five entries at one weight, so the handful a sheikh
    opens daily were lost among the ones he opens once a term. Nothing was
    taken away; it is only ordered by how often it is wanted.
--}}
@php
    use App\Models\StudentPlacementRequest;
    use App\Services\MessagingService;
    use App\Support\RolePages;
    use App\Support\Scope;

    $shows = fn (string $page) => RolePages::isEnabled('supervisor', $page);
    $on = fn (string ...$patterns) => request()->routeIs(...$patterns);

    $waitingForPlacement = StudentPlacementRequest::pending()
        ->whereIn('circle_id', Scope::forRole('supervisor')->reachableCircleIds())
        ->count();
    $supervisorUnreadMessages = MessagingService::unreadCountFor('supervisor', auth('supervisor')->id());
@endphp

<flux:sidebar.item icon="home" :href="route('supervisor.dashboard')" :current="$on('supervisor.dashboard')" wire:navigate>
    {{ __('الرئيسية') }}
</flux:sidebar.item>
@if($shows('supervisor.students'))
    <flux:sidebar.item icon="academic-cap" :href="route('supervisor.students')" :current="$on('supervisor.students')"
        :badge="$waitingForPlacement > 0 ? $waitingForPlacement : null" badge:color="red" wire:navigate>
        {{ __('الطلاب') }}
    </flux:sidebar.item>
@endif
@if($shows('supervisor.circles'))
    <flux:sidebar.item icon="circle-stack" :href="route('supervisor.circles')" :current="$on('supervisor.circles')" wire:navigate>
        {{ __('الدفعات') }}
    </flux:sidebar.item>
@endif
@if($shows('supervisor.teachers'))
    <flux:sidebar.item icon="users" :href="route('supervisor.teachers')" :current="$on('supervisor.teachers')" wire:navigate>
        {{ __('المعلمون') }}
    </flux:sidebar.item>
@endif

<flux:sidebar.group heading="المتابعة" class="grid">
    @if($shows('supervisor.reports'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('supervisor.reports')" :current="$on('supervisor.reports')" wire:navigate>
            {{ __('التقارير') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.messages'))
        <flux:sidebar.item icon="envelope" :href="route('supervisor.messages')" :current="$on('supervisor.messages')"
            :badge="$supervisorUnreadMessages > 0 ? $supervisorUnreadMessages : null" badge-color="rose" wire:navigate>
            {{ __('الرسائل') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.yearly-attendance'))
        <flux:sidebar.item icon="calendar" :href="route('supervisor.yearly-attendance')" :current="$on('supervisor.yearly-attendance')" wire:navigate>
            {{ __('متابعة تحضير الدفعات') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.academic-calendar'))
        <flux:sidebar.item icon="calendar-days" :href="route('supervisor.academic-calendar')" :current="$on('supervisor.academic-calendar')" wire:navigate>
            {{ __('التقويم الأكاديمي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.competitions'))
        <flux:sidebar.item icon="trophy" :href="route('supervisor.competitions')" :current="$on('supervisor.competitions') && request()->query('create_gamification') !== '1'" wire:navigate>
            {{ __('المسابقات') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="البرنامج الذاتي" expandable :expanded="$on('supervisor.self-program-*', 'supervisor.period-values')" class="grid">
    @if($shows('supervisor.self-program-weeks'))
        <flux:sidebar.item icon="squares-2x2" :href="route('supervisor.self-program-weeks')" :current="$on('supervisor.self-program-weeks')" wire:navigate>
            {{ __('البرنامج الذاتي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.self-program-progress'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('supervisor.self-program-progress')" :current="$on('supervisor.self-program-progress')" wire:navigate>
            {{ __('تقدّم البرنامج') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.self-program-record'))
        <flux:sidebar.item icon="clipboard-document-check" :href="route('supervisor.self-program-record')" :current="$on('supervisor.self-program-record')" wire:navigate>
            {{ __('سجلّ الطالب') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.self-program-tracks'))
        <flux:sidebar.item icon="adjustments-horizontal" :href="route('supervisor.self-program-tracks')" :current="$on('supervisor.self-program-tracks')" wire:navigate>
            {{ __('مجالات كتابة البرنامج الذاتي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.period-values'))
        <flux:sidebar.item icon="sparkles" :href="route('supervisor.period-values')" :current="$on('supervisor.period-values')" wire:navigate>
            {{ __('قيم الفترة') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="المحتوى العلمي" expandable :expanded="$on('supervisor.odes*', 'supervisor.hadiths*')" class="grid">
    @if($shows('supervisor.odes'))
        <flux:sidebar.item icon="book-open" :href="route('supervisor.odes')" :current="$on('supervisor.odes') && ! $on('supervisor.odes.plans', 'supervisor.odes.create-plan', 'supervisor.odes.paths')" wire:navigate>
            {{ __('إدارة المنظومات') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.odes.paths'))
        <flux:sidebar.item icon="map" :href="route('supervisor.odes.paths')" :current="$on('supervisor.odes.paths*')" wire:navigate>
            {{ __('مسارات حفظ المنظومات') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.hadiths'))
        <flux:sidebar.item icon="document-text" :href="route('supervisor.hadiths')" :current="$on('supervisor.hadiths') && ! $on('supervisor.hadiths.create-plan', 'supervisor.hadiths.paths')" wire:navigate>
            {{ __('إدارة الأحاديث') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.hadiths.paths'))
        <flux:sidebar.item icon="map" :href="route('supervisor.hadiths.paths')" :current="$on('supervisor.hadiths.paths*')" wire:navigate>
            {{ __('مسارات حفظ المتون') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.odes.plans'))
        <flux:sidebar.item icon="clipboard-document-list" :href="route('supervisor.odes.plans')" :current="$on('supervisor.odes.plans*', 'supervisor.odes.create-plan*')" wire:navigate>
            {{ __('خطط المنظومات المنشأة') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="المهام واليوم" expandable :expanded="$on('supervisor.my-day', 'supervisor.task-board', 'supervisor.task-automation', 'supervisor.tasks', 'supervisor.exceeded-limits')" class="grid">
    @if($shows('supervisor.my-day'))
        <flux:sidebar.item icon="sun" :href="route('supervisor.my-day')" :current="$on('supervisor.my-day')" wire:navigate>
            {{ __('يومي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.task-board'))
        <flux:sidebar.item icon="rectangle-stack" :href="route('supervisor.task-board')" :current="$on('supervisor.task-board')" wire:navigate>
            {{ __('لوحة المهام') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.task-automation'))
        <flux:sidebar.item icon="arrow-path" :href="route('supervisor.task-automation')" :current="$on('supervisor.task-automation')" wire:navigate>
            {{ __('المهام التلقائية') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.tasks'))
        <flux:sidebar.item icon="clipboard-document-list" :href="route('supervisor.tasks')" :current="$on('supervisor.tasks')" wire:navigate>
            {{ __('المهام') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.exceeded-limits'))
        <flux:sidebar.item icon="exclamation-triangle" :href="route('supervisor.exceeded-limits')" :current="$on('supervisor.exceeded-limits')" wire:navigate>
            {{ __('لائحة التجاوزات') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="التواصل والسجل" expandable :expanded="$on('supervisor.student-log', 'supervisor.portal', 'supervisor.motivations')" class="grid">
    @if($shows('supervisor.student-log'))
        <flux:sidebar.item icon="book-open" :href="route('supervisor.student-log')" :current="$on('supervisor.student-log')" wire:navigate>
            {{ __('السجل التربوي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.portal'))
        <flux:sidebar.item icon="megaphone" :href="route('supervisor.portal')" :current="$on('supervisor.portal')" wire:navigate>
            {{ __('بوابة الرسائل') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.motivations'))
        <flux:sidebar.item icon="sparkles" :href="route('supervisor.motivations')" :current="$on('supervisor.motivations')" wire:navigate>
            {{ __('مستودع الشواهد') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="المسابقات والإعدادات" expandable :expanded="$on('supervisor.teacher-competitions*', 'supervisor.competitions.gamification', 'supervisor.placement-requests', 'supervisor.event-visibility', 'supervisor.whatsapp-settings', 'supervisor.forms*') || ($on('supervisor.competitions') && request()->query('create_gamification') === '1')" class="grid">
    @if($shows('supervisor.competitions'))
        <flux:sidebar.item icon="plus-circle" :href="route('supervisor.competitions', ['create_gamification' => 1])" :current="$on('supervisor.competitions') && request()->query('create_gamification') === '1'" wire:navigate>
            {{ __('إنشاء مسابقة تلعيب') }}
        </flux:sidebar.item>
        @php
            $latestGamification = \App\Models\Leaderboard::where('competition_type', 'gamification')
                ->where('supervisor_id', auth('supervisor')->id())
                ->latest()
                ->first();
        @endphp
        @if($latestGamification)
            <flux:sidebar.item icon="sparkles" :href="route('supervisor.competitions.gamification', $latestGamification->id)" :current="$on('supervisor.competitions.gamification')" wire:navigate>
                {{ __('إدارة التلعيب') }}
            </flux:sidebar.item>
        @endif
    @endif
    @if($shows('supervisor.teacher-competitions'))
        <flux:sidebar.item icon="trophy" :href="route('supervisor.teacher-competitions')" :current="$on('supervisor.teacher-competitions*')" wire:navigate>
            {{ __('مسابقة المعلمين') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.placement-requests'))
        <flux:sidebar.item icon="user-plus" :href="route('supervisor.placement-requests')" :current="$on('supervisor.placement-requests')" wire:navigate>
            {{ __('طلبات التسكين') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.event-visibility'))
        <flux:sidebar.item icon="eye" :href="route('supervisor.event-visibility')" :current="$on('supervisor.event-visibility')" wire:navigate>
            {{ __('رؤية الأحداث') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.whatsapp-settings'))
        <flux:sidebar.item icon="chat-bubble-left-right" :href="route('supervisor.whatsapp-settings')" :current="$on('supervisor.whatsapp-settings')" wire:navigate>
            {{ __('إعدادات الواتساب') }}
        </flux:sidebar.item>
    @endif
    @if($shows('supervisor.forms'))
        <flux:sidebar.item icon="document-text" :href="route('supervisor.forms')" :current="$on('supervisor.forms*')" wire:navigate>
            {{ __('إدارة النماذج') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<x-held-screens-nav />
