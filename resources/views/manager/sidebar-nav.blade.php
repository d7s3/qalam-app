<flux:sidebar.item icon="home" :href="route('manager.dashboard')" :current="request()->routeIs('manager.dashboard')"
    wire:navigate>
    الرئيسية
</flux:sidebar.item>
@php
    $managerUnreadMessages = \App\Services\MessagingService::unreadCountFor('manager', auth('manager')->id());
@endphp
@if(\App\Support\RolePages::isEnabled('manager', 'manager.messages'))
    <flux:sidebar.item icon="envelope" :href="route('manager.messages')" :current="request()->routeIs('manager.messages')"
        :badge="$managerUnreadMessages > 0 ? $managerUnreadMessages : null" badge-color="rose" wire:navigate>
        الرسائل
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.stages'))
    <flux:sidebar.item icon="rectangle-stack" :href="route('manager.stages')"
        :current="request()->routeIs('manager.stages')" wire:navigate>
        المراحل التعليمية
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.circles'))
    <flux:sidebar.item icon="circle-stack" :href="route('manager.circles')" :current="request()->routeIs('manager.circles')"
        wire:navigate>
        الحلقات
    </flux:sidebar.item>
@endif
<flux:sidebar.group heading="المستخدمين" class="grid">

    @if(\App\Support\RolePages::isEnabled('manager', 'manager.students') || \App\Support\RolePages::isEnabled('manager', 'manager.teachers') || \App\Support\RolePages::isEnabled('manager', 'manager.supervisors') || \App\Support\RolePages::isEnabled('manager', 'manager.guardians'))
        <flux:sidebar.item icon="users" :href="route('manager.students')"
            :current="request()->routeIs(['manager.students', 'manager.teachers', 'manager.supervisors', 'manager.guardians'])" wire:navigate>
            المستخدمون
        </flux:sidebar.item>
    @endif
    @php
        $pendingRequestsCount = \App\Models\Student::where('is_approved', false)->where('is_rejected', false)->count()
            + \App\Models\Teacher::where('is_approved', false)->where('is_rejected', false)->count()
            + \App\Models\Supervisor::where('is_approved', false)->where('is_rejected', false)->count()
            + \App\Models\Guardian::where('is_approved', false)->where('is_rejected', false)->count();
    @endphp
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.pending-approvals'))
        <flux:sidebar.item icon="user-plus" :href="route('manager.pending-approvals')"
            :current="request()->routeIs('manager.pending-approvals')"
            :badge="$pendingRequestsCount > 0 ? $pendingRequestsCount : null" badge-color="amber" wire:navigate>
            طلبات التسجيل
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
<flux:sidebar.group heading="الاختبارات" class="grid">
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.exam-levels'))
        <flux:sidebar.item icon="document-text" :href="route('manager.exam-levels')"
            :current="request()->routeIs('manager.exam-levels')" wire:navigate>
            مستويات الاختبارات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.student-exams'))
        <flux:sidebar.item icon="academic-cap" :href="route('manager.student-exams')"
            :current="request()->routeIs('manager.student-exams')" wire:navigate>
            اختبارات الطلاب
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
<flux:sidebar.group heading="التقارير" class="grid">
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.attendance-reports'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('manager.attendance-reports')"
            :current="request()->routeIs('manager.attendance-reports')" wire:navigate>
            تقارير الحضور والغياب
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.yearly-attendance'))
        <flux:sidebar.item icon="calendar" :href="route('manager.yearly-attendance')"
            :current="request()->routeIs('manager.yearly-attendance')" wire:navigate>
            متابعة الحلقات السنوي
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.academic-calendar'))
        <flux:sidebar.item icon="calendar-days" :href="route('manager.academic-calendar')"
            :current="request()->routeIs('manager.academic-calendar')" wire:navigate>
            التقويم الأكاديمي
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.tasks'))
        <flux:sidebar.item icon="clipboard-document-list" :href="route('manager.tasks')"
            :current="request()->routeIs('manager.tasks')" wire:navigate>
            المهام
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.quranic-achievement'))
        <flux:sidebar.item icon="document-chart-bar" :href="route('manager.quranic-achievement')"
            :current="request()->routeIs('manager.quranic-achievement')" wire:navigate>
            تقرير الإنجاز القرآني
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.exceeded-limits'))
        <flux:sidebar.item icon="exclamation-triangle" :href="route('manager.exceeded-limits')"
            :current="request()->routeIs('manager.exceeded-limits')" wire:navigate>
            لائحة التجاوزات
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
@if(\App\Support\RolePages::isEnabled('manager', 'manager.ai-analysis'))
    <flux:sidebar.group heading="التحليل" class="grid">
        <flux:sidebar.item icon="sparkles" :href="route('manager.ai-analysis')"
            :current="request()->routeIs('manager.ai-analysis')" wire:navigate>
            الالتحليل الذكي
        </flux:sidebar.item>
    </flux:sidebar.group>
@endif

@if(\App\Support\RolePages::isEnabled('manager', 'manager.quran-editor'))
    <flux:sidebar.group heading="بيانات المصحف" class="grid">
        <flux:sidebar.item icon="book-open" :href="route('manager.quran-editor')"
            :current="request()->routeIs('manager.quran-editor')" wire:navigate>
            محرر الأسطر
        </flux:sidebar.item>
    </flux:sidebar.group>
@endif

<flux:sidebar.group heading="إدارة النظام" class="grid">
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.settings'))
        <flux:sidebar.item icon="cog" :href="route('manager.settings')" :current="request()->routeIs('manager.settings')"
            wire:navigate>
            إعدادات الانضباط
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.whatsapp-settings'))
        <flux:sidebar.item icon="chat-bubble-left-right" :href="route('manager.whatsapp-settings')" :current="request()->routeIs('manager.whatsapp-settings')"
            wire:navigate>
            إعدادات الواتساب
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.api-docs'))
        <flux:sidebar.item icon="document-text" :href="route('manager.api-docs')" :current="request()->routeIs('manager.api-docs')"
            wire:navigate>
            توثيق الـ API
        </flux:sidebar.item>
    @endif
    <flux:sidebar.item icon="shield-check" :href="route('manager.role-permissions')" :current="request()->routeIs('manager.role-permissions')"
        wire:navigate>
        صلاحيات الصفحات
    </flux:sidebar.item>
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.staff-members'))
        <flux:sidebar.item icon="identification" :href="route('manager.staff-members')" :current="request()->routeIs('manager.staff-members')"
            wire:navigate>
            إدارة الموظفين
        </flux:sidebar.item>
    @endif

</flux:sidebar.group>
