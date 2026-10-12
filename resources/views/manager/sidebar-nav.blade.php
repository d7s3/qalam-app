{{--
    The manager's sidebar: the academy's people and the requests waiting on
    him first, the rest folded into groups that open on their own when he is
    on one of them.

    It ran to thirty-eight entries, longer than the screen, with the users and
    the registration requests halfway down. Nothing was taken away; it is only
    ordered by how often it is wanted.
--}}
@php
    use App\Models\Guardian;
    use App\Models\Student;
    use App\Models\Supervisor;
    use App\Models\Teacher;
    use App\Services\MessagingService;
    use App\Support\RolePages;

    $shows = fn (string $page) => RolePages::isEnabled('manager', $page);
    $on = fn (string ...$patterns) => request()->routeIs(...$patterns);

    $managerUnreadMessages = MessagingService::unreadCountFor('manager', auth('manager')->id());
    $pendingRequestsCount = collect([Student::class, Teacher::class, Supervisor::class, Guardian::class])
        ->sum(fn (string $model) => $model::where('is_approved', false)->where('is_rejected', false)->count());
@endphp

<flux:sidebar.item icon="home" :href="route('manager.dashboard')" :current="$on('manager.dashboard')" wire:navigate>
    {{ __('الرئيسية') }}
</flux:sidebar.item>
@if($shows('manager.students') || $shows('manager.teachers') || $shows('manager.supervisors') || $shows('manager.guardians'))
    <flux:sidebar.item icon="users" :href="route('manager.students')"
        :current="$on('manager.students', 'manager.teachers', 'manager.supervisors', 'manager.guardians', 'manager.managers')" wire:navigate>
        {{ __('المستخدمون') }}
    </flux:sidebar.item>
@endif
@if($shows('manager.pending-approvals'))
    <flux:sidebar.item icon="user-plus" :href="route('manager.pending-approvals')" :current="$on('manager.pending-approvals')"
        :badge="$pendingRequestsCount > 0 ? $pendingRequestsCount : null" badge-color="amber" wire:navigate>
        {{ __('طلبات التسجيل') }}
    </flux:sidebar.item>
@endif
@if($shows('manager.circles'))
    <flux:sidebar.item icon="circle-stack" :href="route('manager.circles')" :current="$on('manager.circles')" wire:navigate>
        {{ __('الدفعات') }}
    </flux:sidebar.item>
@endif
@if($shows('manager.stages'))
    <flux:sidebar.item icon="rectangle-stack" :href="route('manager.stages')" :current="$on('manager.stages')" wire:navigate>
        {{ __('البرامج') }}
    </flux:sidebar.item>
@endif
@if($shows('manager.messages'))
    <flux:sidebar.item icon="envelope" :href="route('manager.messages')" :current="$on('manager.messages')"
        :badge="$managerUnreadMessages > 0 ? $managerUnreadMessages : null" badge-color="rose" wire:navigate>
        {{ __('الرسائل') }}
    </flux:sidebar.item>
@endif

<flux:sidebar.group heading="المتابعة" class="grid">
    @if($shows('manager.reports'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('manager.reports')" :current="$on('manager.reports')" wire:navigate>
            {{ __('التقارير') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.attendance-reports'))
        <flux:sidebar.item icon="chart-bar" :href="route('manager.attendance-reports')" :current="$on('manager.attendance-reports')" wire:navigate>
            {{ __('تقارير الحضور والغياب') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.yearly-attendance'))
        <flux:sidebar.item icon="calendar" :href="route('manager.yearly-attendance')" :current="$on('manager.yearly-attendance')" wire:navigate>
            {{ __('متابعة تحضير الدفعات') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.academic-calendar'))
        <flux:sidebar.item icon="calendar-days" :href="route('manager.academic-calendar')" :current="$on('manager.academic-calendar')" wire:navigate>
            {{ __('التقويم الأكاديمي') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="الاختبارات والقرآن" expandable :expanded="$on('manager.exam-levels', 'manager.student-exams', 'manager.quranic-achievement', 'manager.quran-editor', 'manager.ai-analysis')" class="grid">
    @if($shows('manager.student-exams'))
        <flux:sidebar.item icon="academic-cap" :href="route('manager.student-exams')" :current="$on('manager.student-exams')" wire:navigate>
            {{ __('اختبارات الطلاب') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.exam-levels'))
        <flux:sidebar.item icon="document-text" :href="route('manager.exam-levels')" :current="$on('manager.exam-levels')" wire:navigate>
            {{ __('مستويات الاختبارات') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.quranic-achievement'))
        <flux:sidebar.item icon="document-chart-bar" :href="route('manager.quranic-achievement')" :current="$on('manager.quranic-achievement')" wire:navigate>
            {{ __('تقرير الإنجاز القرآني') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.ai-analysis'))
        <flux:sidebar.item icon="sparkles" :href="route('manager.ai-analysis')" :current="$on('manager.ai-analysis')" wire:navigate>
            {{ __('التحليل الذكي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.quran-editor'))
        <flux:sidebar.item icon="book-open" :href="route('manager.quran-editor')" :current="$on('manager.quran-editor')" wire:navigate>
            {{ __('محرر أسطر المصحف') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="البرنامج الذاتي" expandable :expanded="$on('manager.self-program-*', 'manager.period-values')" class="grid">
    @if($shows('manager.self-program-weeks'))
        <flux:sidebar.item icon="pencil-square" :href="route('manager.self-program-weeks')" :current="$on('manager.self-program-weeks')" wire:navigate>
            {{ __('كتابة البرنامج الذاتي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.self-program-progress'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('manager.self-program-progress')" :current="$on('manager.self-program-progress')" wire:navigate>
            {{ __('تقدّم البرنامج') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.self-program-record'))
        <flux:sidebar.item icon="clipboard-document-check" :href="route('manager.self-program-record')" :current="$on('manager.self-program-record')" wire:navigate>
            {{ __('سجلّ الطالب') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.self-program-tracks'))
        <flux:sidebar.item icon="adjustments-horizontal" :href="route('manager.self-program-tracks')" :current="$on('manager.self-program-tracks')" wire:navigate>
            {{ __('مجالات البرنامج الذاتي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.period-values'))
        <flux:sidebar.item icon="sparkles" :href="route('manager.period-values')" :current="$on('manager.period-values')" wire:navigate>
            {{ __('قيم الفترة') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="المهام واليوم" expandable :expanded="$on('manager.my-day', 'manager.task-board', 'manager.task-automation', 'manager.tasks', 'manager.exceeded-limits')" class="grid">
    @if($shows('manager.my-day'))
        <flux:sidebar.item icon="sun" :href="route('manager.my-day')" :current="$on('manager.my-day')" wire:navigate>
            {{ __('يومي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.task-board'))
        <flux:sidebar.item icon="rectangle-stack" :href="route('manager.task-board')" :current="$on('manager.task-board')" wire:navigate>
            {{ __('لوحة المهام') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.task-automation'))
        <flux:sidebar.item icon="arrow-path" :href="route('manager.task-automation')" :current="$on('manager.task-automation')" wire:navigate>
            {{ __('المهام التلقائية') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.tasks'))
        <flux:sidebar.item icon="clipboard-document-list" :href="route('manager.tasks')" :current="$on('manager.tasks')" wire:navigate>
            {{ __('المهام') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.exceeded-limits'))
        <flux:sidebar.item icon="exclamation-triangle" :href="route('manager.exceeded-limits')" :current="$on('manager.exceeded-limits')" wire:navigate>
            {{ __('لائحة التجاوزات') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="التواصل والسجل" expandable :expanded="$on('manager.portal', 'manager.student-log', 'manager.motivations', 'manager.event-visibility', 'manager.forms*')" class="grid">
    @if($shows('manager.portal'))
        <flux:sidebar.item icon="megaphone" :href="route('manager.portal')" :current="$on('manager.portal')" wire:navigate>
            {{ __('بوابة الرسائل') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.student-log'))
        <flux:sidebar.item icon="book-open" :href="route('manager.student-log')" :current="$on('manager.student-log')" wire:navigate>
            {{ __('السجل التربوي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.motivations'))
        <flux:sidebar.item icon="sparkles" :href="route('manager.motivations')" :current="$on('manager.motivations')" wire:navigate>
            {{ __('مستودع الشواهد') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.event-visibility'))
        <flux:sidebar.item icon="eye" :href="route('manager.event-visibility')" :current="$on('manager.event-visibility')" wire:navigate>
            {{ __('رؤية الأحداث') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.forms'))
        <flux:sidebar.item icon="document-text" :href="route('manager.forms')" :current="$on('manager.forms*')" wire:navigate>
            {{ __('الاستبانات والنماذج') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="الصلاحيات والإعدادات" expandable :expanded="$on('manager.user-access', 'manager.role-permissions', 'manager.stage-access', 'manager.staff-members', 'manager.settings', 'manager.whatsapp-settings', 'manager.ai-settings', 'manager.api-docs')" class="grid">
    @if($shows('manager.user-access'))
        <flux:sidebar.item icon="shield-check" :href="route('manager.user-access')" :current="$on('manager.user-access')" wire:navigate>
            {{ __('صلاحيات المستخدمين') }}
        </flux:sidebar.item>
    @endif
    <flux:sidebar.item icon="shield-check" :href="route('manager.role-permissions')" :current="$on('manager.role-permissions')" wire:navigate>
        {{ __('صلاحيات الصفحات') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="rectangle-group" :href="route('manager.stage-access')" :current="$on('manager.stage-access')" wire:navigate>
        {{ __('صلاحيات البرامج') }}
    </flux:sidebar.item>
    @if($shows('manager.staff-members'))
        <flux:sidebar.item icon="identification" :href="route('manager.staff-members')" :current="$on('manager.staff-members')" wire:navigate>
            {{ __('إدارة الموظفين') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.settings'))
        <flux:sidebar.item icon="cog" :href="route('manager.settings')" :current="$on('manager.settings')" wire:navigate>
            {{ __('الانضباط والنسخ الاحتياطي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.whatsapp-settings'))
        <flux:sidebar.item icon="chat-bubble-left-right" :href="route('manager.whatsapp-settings')" :current="$on('manager.whatsapp-settings')" wire:navigate>
            {{ __('إعدادات الواتساب') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.ai-settings'))
        <flux:sidebar.item icon="sparkles" :href="route('manager.ai-settings')" :current="$on('manager.ai-settings')" wire:navigate>
            {{ __('إعدادات الذكاء الاصطناعي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('manager.api-docs'))
        <flux:sidebar.item icon="document-text" :href="route('manager.api-docs')" :current="$on('manager.api-docs')" wire:navigate>
            {{ __('توثيق الـ API') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<x-held-screens-nav />
