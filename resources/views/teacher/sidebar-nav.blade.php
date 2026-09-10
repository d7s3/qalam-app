<flux:sidebar.group heading="التعليم" class="grid">
    <flux:sidebar.item icon="home" wire:navigate :current="request()->routeIs('teacher.dashboard')"
        href="{{ route('teacher.dashboard') }}">
        {{ __('الرئيسية') }}
    </flux:sidebar.item>
@if(\App\Support\RolePages::isEnabled('teacher', 'teacher.self-program-weeks'))
    <flux:sidebar.item icon="pencil-square" :href="route('teacher.self-program-weeks')" :current="request()->routeIs('teacher.self-program-weeks')" wire:navigate>
        كتابة البرنامج الذاتي
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('teacher', 'teacher.student-log'))
    <flux:sidebar.item icon="book-open" :href="route('teacher.student-log')" :current="request()->routeIs('teacher.student-log')" wire:navigate>
        السجل التربوي
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('teacher', 'teacher.portal'))
    <flux:sidebar.item icon="megaphone" :href="route('teacher.portal')" :current="request()->routeIs('teacher.portal')" wire:navigate>
        بوابة الرسائل
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('teacher', 'teacher.motivations'))
    <flux:sidebar.item icon="sparkles" :href="route('teacher.motivations')" :current="request()->routeIs('teacher.motivations')" wire:navigate>
        مستودع الشواهد
    </flux:sidebar.item>
@endif
    @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.my-day'))
        <flux:sidebar.item icon="sun" :href="route('teacher.my-day')" :current="request()->routeIs('teacher.my-day')" wire:navigate>
            {{ __('يومي') }}
        </flux:sidebar.item>
    @endif
@if(\App\Support\RolePages::isEnabled('teacher', 'teacher.task-board'))
    <flux:sidebar.item icon="rectangle-stack" :href="route('teacher.task-board')" :current="request()->routeIs('teacher.task-board')" wire:navigate>
        لوحة المهام
    </flux:sidebar.item>
@endif
@if(\App\Support\RolePages::isEnabled('teacher', 'teacher.task-automation'))
    <flux:sidebar.item icon="arrow-path" :href="route('teacher.task-automation')" :current="request()->routeIs('teacher.task-automation')" wire:navigate>
        المهام التلقائية
    </flux:sidebar.item>
@endif
    @php
        $teacherUnreadMessages = \App\Services\MessagingService::unreadCountFor('teacher', auth('teacher')->id());
    @endphp
    @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.messages'))
        <flux:sidebar.item icon="envelope" wire:navigate :current="request()->routeIs('teacher.messages')"
            :badge="$teacherUnreadMessages > 0 ? $teacherUnreadMessages : null" badge-color="rose"
            href="{{ route('teacher.messages') }}">
            {{ __('الرسائل') }}
        </flux:sidebar.item>
    @endif
    <flux:sidebar.group heading="{{ __('الخطط القرآنية') }}" class="mt-4">
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.students'))
            <flux:sidebar.item icon="users"
                x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'students', url: '{{ route('teacher.students') }}' }); } else { Livewire.navigate('{{ route('teacher.students') }}'); }"
                x-bind:data-current="'{{ $initialTab ?? '' }}' === 'students' ? 'true' : null"
                x-on:switch-tab.window="if($event.detail.tab === 'students') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
                href="{{ route('teacher.students') }}">
                {{ __('إدارة الطلاب') }}
            </flux:sidebar.item>
        @endif
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.plan-creator'))
            <flux:sidebar.item icon="pencil-square"
                x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'plan-creator', url: '{{ route('teacher.plan-creator') }}' }); } else { Livewire.navigate('{{ route('teacher.plan-creator') }}'); }"
                x-bind:data-current="'{{ $initialTab ?? '' }}' === 'plan-creator' ? 'true' : null"
                x-on:switch-tab.window="if($event.detail.tab === 'plan-creator') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
                href="{{ route('teacher.plan-creator') }}">
                {{ __('إنشاء خطة طالب') }}
            </flux:sidebar.item>
        @endif
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.self-program'))
            <flux:sidebar.item icon="squares-2x2" wire:navigate
                :href="route('teacher.self-program')" :current="request()->routeIs('teacher.self-program')">
                {{ __('البرنامج الذاتي') }}
            </flux:sidebar.item>
        @endif
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.student-plans'))
            <flux:sidebar.item icon="clipboard-document-list" wire:navigate
                :current="request()->routeIs('teacher.student-plans')" href="{{ route('teacher.student-plans') }}">
                {{ __('عرض الخطط المنشأة') }}
            </flux:sidebar.item>
        @endif
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.tasmeeh'))
            <flux:sidebar.item icon="book-open"
                x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'tasmeeh', url: '{{ route('teacher.tasmeeh') }}' }); } else { Livewire.navigate('{{ route('teacher.tasmeeh') }}'); }"
                x-bind:data-current="'{{ $initialTab ?? '' }}' === 'tasmeeh' ? 'true' : null"
                x-on:switch-tab.window="if($event.detail.tab === 'tasmeeh') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
                href="{{ route('teacher.tasmeeh') }}">
                {{ __('التسميع والمتابعة') }}
            </flux:sidebar.item>
        @endif
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.pairs'))
            <flux:sidebar.item icon="users" wire:navigate :current="request()->routeIs('teacher.pairs')"
                href="{{ route('teacher.pairs') }}">
                {{ __('التسميع المتبادل') }}
            </flux:sidebar.item>
        @endif
    </flux:sidebar.group>

    @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.ode-plans'))
        <flux:sidebar.group heading="{{ __('خطط المنظومات') }}" class="mt-4">
            <flux:sidebar.item icon="clipboard-document-list" wire:navigate
                :current="request()->routeIs('teacher.ode-plans')" href="{{ route('teacher.ode-plans') }}">
                {{ __('عرض الخطط المنشأة') }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    @endif

    @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.leaderboards'))
        <flux:sidebar.group heading="{{ __('التحفيز والمنافسة') }}" class="mt-4">
            <flux:sidebar.item icon="trophy"
                x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'leaderboards', url: '{{ route('teacher.leaderboards') }}' }); } else { Livewire.navigate('{{ route('teacher.leaderboards') }}'); }"
                x-bind:data-current="'{{ $initialTab ?? '' }}' === 'leaderboards' ? 'true' : null"
                x-on:switch-tab.window="if($event.detail.tab === 'leaderboards') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
                href="{{ route('teacher.leaderboards') }}">
                {{ __('مسابقات الدفعة') }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    @endif

    @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.student-exams'))
        <flux:sidebar.group heading="{{ __('الاختبارات') }}" class="mt-4">
            <flux:sidebar.item icon="academic-cap" wire:navigate :current="request()->routeIs('teacher.student-exams*')"
                href="{{ route('teacher.student-exams') }}">
                {{ __('اختبارات الطلاب') }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    @endif

    <flux:sidebar.group heading="{{ __('التحضير') }}" class="mt-4">
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.attendance'))
            <flux:sidebar.item icon="calendar"
                x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'attendance', url: '{{ route('teacher.attendance') }}' }); } else { Livewire.navigate('{{ route('teacher.attendance') }}'); }"
                x-bind:data-current="'{{ $initialTab ?? '' }}' === 'attendance' ? 'true' : null"
                x-on:switch-tab.window="if($event.detail.tab === 'attendance') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
                href="{{ route('teacher.attendance') }}">
                سجل الحضور
            </flux:sidebar.item>
        @endif
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.discipline'))
            <flux:sidebar.item icon="chart-bar" wire:navigate :current="request()->routeIs('teacher.discipline')"
                href="{{ route('teacher.discipline') }}">
                الانضباط الحضوري
            </flux:sidebar.item>
        @endif
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.quranic-discipline'))
            <flux:sidebar.item icon="chart-pie" wire:navigate :current="request()->routeIs('teacher.quranic-discipline')"
                href="{{ route('teacher.quranic-discipline') }}">
                الانضباط القرآني
            </flux:sidebar.item>
        @endif
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.exceeded-limits'))
            <flux:sidebar.item icon="exclamation-triangle" wire:navigate
                :current="request()->routeIs('teacher.exceeded-limits')" href="{{ route('teacher.exceeded-limits') }}">
                لائحة التجاوزات
            </flux:sidebar.item>
        @endif
    </flux:sidebar.group>
    @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.forms'))
        <flux:sidebar.item icon="document-text" :href="route('teacher.forms')"
            :current="request()->routeIs('teacher.forms*')" wire:navigate>
            الاستبانات والنماذج
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
        @if(\App\Support\RolePages::isEnabled('teacher', 'teacher.reports'))
            <flux:sidebar.item icon="chart-bar-square" :href="route('teacher.reports')" :current="request()->routeIs('teacher.reports')" wire:navigate>
                {{ __('التقارير') }}
            </flux:sidebar.item>
        @endif
