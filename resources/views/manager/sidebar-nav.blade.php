<flux:sidebar.item class="[&_svg]:bg-[#3b82f6] hover:[&_svg]:bg-[#2563eb]" icon="home" :href="route('manager.dashboard')" :current="request()->routeIs('manager.dashboard')"
    wire:navigate>
    الرئيسية
</flux:sidebar.item>
@if(\App\Support\RolePages::isEnabled('manager', 'manager.portal'))
    <flux:sidebar.item class="[&_svg]:bg-[#e11d48]" icon="megaphone" :href="route('manager.portal')" :current="request()->routeIs('manager.portal')" wire:navigate>
        بوابة الرسائل
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.motivations'))
    <flux:sidebar.item class="[&_svg]:bg-[#f59e0b]" icon="sparkles" :href="route('manager.motivations')" :current="request()->routeIs('manager.motivations')" wire:navigate>
        مستودع الشواهد
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.self-program-tracks'))
    <flux:sidebar.item class="[&_svg]:bg-[#14b8a6] hover:[&_svg]:bg-[#0d9488]" icon="adjustments-horizontal" :href="route('manager.self-program-tracks')" :current="request()->routeIs('manager.self-program-tracks')" wire:navigate>
        مجالات البرنامج الذاتي
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.period-values'))
    <flux:sidebar.item class="[&_svg]:bg-[#d946ef] hover:[&_svg]:bg-[#c026d3]" icon="sparkles" :href="route('manager.period-values')" :current="request()->routeIs('manager.period-values')" wire:navigate>
        قيم الفترة
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.event-visibility'))
    <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="eye" :href="route('manager.event-visibility')" :current="request()->routeIs('manager.event-visibility')" wire:navigate>
        رؤية الأحداث
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.my-day'))
    <flux:sidebar.item class="[&_svg]:bg-[#f59e0b] hover:[&_svg]:bg-[#d97706]" icon="sun" :href="route('manager.my-day')" :current="request()->routeIs('manager.my-day')" wire:navigate>
        {{ __('يومي') }}
    </flux:sidebar.item>
@endif
@php
    $managerUnreadMessages = \App\Services\MessagingService::unreadCountFor('manager', auth('manager')->id());
@endphp
@if(\App\Support\RolePages::isEnabled('manager', 'manager.messages'))
    <flux:sidebar.item class="[&_svg]:bg-[#e11d48] hover:[&_svg]:bg-[#be123c]" icon="envelope" :href="route('manager.messages')" :current="request()->routeIs('manager.messages')"
        :badge="$managerUnreadMessages > 0 ? $managerUnreadMessages : null" badge-color="rose" wire:navigate>
        الرسائل
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.stages'))
    <flux:sidebar.item class="[&_svg]:bg-[#8b5cf6] hover:[&_svg]:bg-[#7c3aed]" icon="rectangle-stack" :href="route('manager.stages')"
        :current="request()->routeIs('manager.stages')" wire:navigate>
        البرامج
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.self-program-progress'))
    <flux:sidebar.item class="[&_svg]:bg-[#10b981] hover:[&_svg]:bg-[#059669]" icon="chart-bar-square" :href="route('manager.self-program-progress')" :current="request()->routeIs('manager.self-program-progress')" wire:navigate>
        {{ __('تقدّم البرنامج') }}
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.circles'))
    <flux:sidebar.item class="[&_svg]:bg-[#0d9488] hover:[&_svg]:bg-[#0f766e]" icon="circle-stack" :href="route('manager.circles')" :current="request()->routeIs('manager.circles')"
        wire:navigate>
        الدفعات
    </flux:sidebar.item>
@endif
<flux:sidebar.group heading="المستخدمين" class="grid">

    @if(\App\Support\RolePages::isEnabled('manager', 'manager.students') || \App\Support\RolePages::isEnabled('manager', 'manager.teachers') || \App\Support\RolePages::isEnabled('manager', 'manager.supervisors') || \App\Support\RolePages::isEnabled('manager', 'manager.guardians'))
        <flux:sidebar.item class="[&_svg]:bg-[#a855f7] hover:[&_svg]:bg-[#9333ea]" icon="users" :href="route('manager.students')"
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
        <flux:sidebar.item class="[&_svg]:bg-[#fb923c] hover:[&_svg]:bg-[#f97316]" icon="user-plus" :href="route('manager.pending-approvals')"
            :current="request()->routeIs('manager.pending-approvals')"
            :badge="$pendingRequestsCount > 0 ? $pendingRequestsCount : null" badge-color="amber" wire:navigate>
            طلبات التسجيل
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
<flux:sidebar.group heading="الاختبارات" class="grid">
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.exam-levels'))
        <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="document-text" :href="route('manager.exam-levels')"
            :current="request()->routeIs('manager.exam-levels')" wire:navigate>
            مستويات الاختبارات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.student-exams'))
        <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="academic-cap" :href="route('manager.student-exams')"
            :current="request()->routeIs('manager.student-exams')" wire:navigate>
            اختبارات الطلاب
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
<flux:sidebar.group heading="التقارير" class="grid">
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.attendance-reports'))
        <flux:sidebar.item class="[&_svg]:bg-[#10b981] hover:[&_svg]:bg-[#059669]" icon="chart-bar-square" :href="route('manager.attendance-reports')"
            :current="request()->routeIs('manager.attendance-reports')" wire:navigate>
            تقارير الحضور والغياب
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.yearly-attendance'))
        <flux:sidebar.item class="[&_svg]:bg-[#10b981] hover:[&_svg]:bg-[#059669]" icon="calendar" :href="route('manager.yearly-attendance')"
            :current="request()->routeIs('manager.yearly-attendance')" wire:navigate>
            متابعة تحضير الدفعات
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.academic-calendar'))
        <flux:sidebar.item class="[&_svg]:bg-[#f59e0b] hover:[&_svg]:bg-[#d97706]" icon="calendar-days" :href="route('manager.academic-calendar')"
            :current="request()->routeIs('manager.academic-calendar')" wire:navigate>
            التقويم الأكاديمي
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.tasks'))
        <flux:sidebar.item class="[&_svg]:bg-[#64748b] hover:[&_svg]:bg-[#475569]" icon="clipboard-document-list" :href="route('manager.tasks')"
            :current="request()->routeIs('manager.tasks')" wire:navigate>
            المهام
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.quranic-achievement'))
        <flux:sidebar.item class="[&_svg]:bg-[#14b8a6] hover:[&_svg]:bg-[#0d9488]" icon="document-chart-bar" :href="route('manager.quranic-achievement')"
            :current="request()->routeIs('manager.quranic-achievement')" wire:navigate>
            تقرير الإنجاز القرآني
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.exceeded-limits'))
        <flux:sidebar.item class="[&_svg]:bg-[#ef4444] hover:[&_svg]:bg-[#dc2626]" icon="exclamation-triangle" :href="route('manager.exceeded-limits')"
            :current="request()->routeIs('manager.exceeded-limits')" wire:navigate>
            لائحة التجاوزات
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
@if(\App\Support\RolePages::isEnabled('manager', 'manager.ai-analysis'))
    <flux:sidebar.group heading="التحليل" class="grid">
        <flux:sidebar.item class="[&_svg]:bg-[#d946ef] hover:[&_svg]:bg-[#c026d3]" icon="sparkles" :href="route('manager.ai-analysis')"
            :current="request()->routeIs('manager.ai-analysis')" wire:navigate>
            التحليل الذكي
        </flux:sidebar.item>
    </flux:sidebar.group>
@endif

@if(\App\Support\RolePages::isEnabled('manager', 'manager.quran-editor'))
    <flux:sidebar.group heading="بيانات المصحف" class="grid">
        <flux:sidebar.item class="[&_svg]:bg-[#0891b2] hover:[&_svg]:bg-[#0e7490]" icon="book-open" :href="route('manager.quran-editor')"
            :current="request()->routeIs('manager.quran-editor')" wire:navigate>
            محرر الأسطر
        </flux:sidebar.item>
    </flux:sidebar.group>
@endif

<flux:sidebar.group heading="إدارة النظام" class="grid">
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.settings'))
        <flux:sidebar.item class="[&_svg]:bg-[#71717a] hover:[&_svg]:bg-[#52525b]" icon="cog" :href="route('manager.settings')" :current="request()->routeIs('manager.settings')"
            wire:navigate>
            الانضباط والنسخ الاحتياطي
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.whatsapp-settings'))
        <flux:sidebar.item class="[&_svg]:bg-[#22c55e] hover:[&_svg]:bg-[#16a34a]" icon="chat-bubble-left-right" :href="route('manager.whatsapp-settings')" :current="request()->routeIs('manager.whatsapp-settings')"
            wire:navigate>
            إعدادات الواتساب
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.ai-settings'))
        <flux:sidebar.item class="[&_svg]:bg-[#d946ef] hover:[&_svg]:bg-[#c026d3]" icon="sparkles" :href="route('manager.ai-settings')" :current="request()->routeIs('manager.ai-settings')"
            wire:navigate>
            إعدادات الذكاء الاصطناعي
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.api-docs'))
        <flux:sidebar.item class="[&_svg]:bg-[#475569] hover:[&_svg]:bg-[#334155]" icon="document-text" :href="route('manager.api-docs')" :current="request()->routeIs('manager.api-docs')"
            wire:navigate>
            توثيق الـ API
        </flux:sidebar.item>
    @endif
    <flux:sidebar.item class="[&_svg]:bg-[#4f46e5] hover:[&_svg]:bg-[#4338ca]" icon="shield-check" :href="route('manager.role-permissions')" :current="request()->routeIs('manager.role-permissions')"
        wire:navigate>
        صلاحيات الصفحات
    </flux:sidebar.item>
    <flux:sidebar.item class="[&_svg]:bg-[#0891b2] hover:[&_svg]:bg-[#0e7490]" icon="rectangle-group" :href="route('manager.stage-access')" :current="request()->routeIs('manager.stage-access')"
        wire:navigate>
        صلاحيات البرامج
    </flux:sidebar.item>
    @if(\App\Support\RolePages::isEnabled('manager', 'manager.staff-members'))
        <flux:sidebar.item class="[&_svg]:bg-[#7c3aed] hover:[&_svg]:bg-[#6d28d9]" icon="identification" :href="route('manager.staff-members')" :current="request()->routeIs('manager.staff-members')"
            wire:navigate>
            إدارة الموظفين
        </flux:sidebar.item>
    @endif

    @if(\App\Support\RolePages::isEnabled('manager', 'manager.forms'))
        <flux:sidebar.item class="[&_svg]:bg-[#14b8a6] hover:[&_svg]:bg-[#0d9488]" icon="document-text" :href="route('manager.forms')"
            :current="request()->routeIs('manager.forms*')" wire:navigate>
            الاستبانات والنماذج
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
@if(\App\Support\RolePages::isEnabled('manager', 'manager.user-access'))
    <flux:sidebar.item class="[&_svg]:bg-[#f59e0b] hover:[&_svg]:bg-[#d97706]" icon="shield-check" :href="route('manager.user-access')"
        :current="request()->routeIs('manager.user-access')" wire:navigate>
        صلاحيات المستخدمين
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('manager', 'manager.reports'))
    <flux:sidebar.item class="[&_svg]:bg-[#0ea5e9] hover:[&_svg]:bg-[#0284c7]" icon="chart-bar-square" :href="route('manager.reports')" :current="request()->routeIs('manager.reports')" wire:navigate>
        {{ __('التقارير') }}
    </flux:sidebar.item>
@endif

<x-held-screens-nav />
