@php
    $studentUnreadMessages = \App\Services\MessagingService::unreadCountFor('student', auth('student')->id());
@endphp

<flux:sidebar.group class="grid">
    <flux:sidebar.item icon="home" :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" wire:navigate>
        {{ __('الرئيسية') }}
    </flux:sidebar.item>
    @if(\App\Support\RolePages::isEnabled('student', 'student.plan'))
        <flux:sidebar.item icon="book-open" :href="route('student.plan')" :current="request()->routeIs('student.plan')" wire:navigate>
            {{ __('مساري القرآني') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('student', 'student.hifz'))
        <flux:sidebar.item icon="bookmark" :href="route('student.hifz')" :current="request()->routeIs('student.hifz')" wire:navigate>
            {{ __('الحفظ') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('student', 'student.review'))
        <flux:sidebar.item icon="arrow-path" :href="route('student.review')" :current="request()->routeIs('student.review')" wire:navigate>
            {{ __('المراجعة') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('student', 'student.exams'))
        <flux:sidebar.item icon="academic-cap" :href="route('student.exams')" :current="request()->routeIs('student.exams')" wire:navigate>
            {{ __('الاختبارات') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('student', 'student.calendar'))
        <flux:sidebar.item icon="calendar" :href="route('student.calendar')" :current="request()->routeIs('student.calendar')" wire:navigate>
            {{ __('التقويم') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('student', 'student.attendance'))
        <flux:sidebar.item icon="clipboard-document-check" :href="route('student.attendance')" :current="request()->routeIs('student.attendance')" wire:navigate>
            {{ __('سجل الانضباط') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('student', 'student.reports'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('student.reports')" :current="request()->routeIs('student.reports')" wire:navigate>
            {{ __('التقارير') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('student', 'student.messages'))
        <flux:sidebar.item icon="envelope" :href="route('student.messages')" :current="request()->routeIs('student.messages')"
            :badge="$studentUnreadMessages > 0 ? $studentUnreadMessages : null" badge-color="rose" wire:navigate>
            {{ __('الرسائل') }}
        </flux:sidebar.item>
    @endif
    <flux:sidebar.item icon="trophy" :href="route('student.dashboard').'#achievements'" wire:navigate>
        {{ __('الإنجازات') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="star" :href="route('student.dashboard').'#leaderboard-standings'" wire:navigate>
        {{ __('المتصدرون') }}
    </flux:sidebar.item>
</flux:sidebar.group>
