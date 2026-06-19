<flux:sidebar.group heading="التعليم" class="grid">
    <flux:sidebar.item icon="home" wire:navigate :current="request()->routeIs('teacher.dashboard')"
        href="{{ route('teacher.dashboard') }}">
        {{ __('الرئيسية') }}
    </flux:sidebar.item>
    <flux:sidebar.group heading="{{ __('الخطط القرآنية') }}" class="mt-4">
        <flux:sidebar.item icon="users"
            x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'students', url: '{{ route('teacher.students') }}' }); } else { Livewire.navigate('{{ route('teacher.students') }}'); }"
            x-bind:data-current="'{{ $initialTab ?? '' }}' === 'students' ? 'true' : null"
            x-on:switch-tab.window="if($event.detail.tab === 'students') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
            href="{{ route('teacher.students') }}">
            {{ __('إدارة الطلاب') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="pencil-square"
            x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'plan-creator', url: '{{ route('teacher.plan-creator') }}' }); } else { Livewire.navigate('{{ route('teacher.plan-creator') }}'); }"
            x-bind:data-current="'{{ $initialTab ?? '' }}' === 'plan-creator' ? 'true' : null"
            x-on:switch-tab.window="if($event.detail.tab === 'plan-creator') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
            href="{{ route('teacher.plan-creator') }}">
            {{ __('إنشاء خطة طالب') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="clipboard-document-list" wire:navigate
            :current="request()->routeIs('teacher.student-plans')" href="{{ route('teacher.student-plans') }}">
            {{ __('عرض الخطط المنشأة') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="book-open"
            x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'tasmeeh', url: '{{ route('teacher.tasmeeh') }}' }); } else { Livewire.navigate('{{ route('teacher.tasmeeh') }}'); }"
            x-bind:data-current="'{{ $initialTab ?? '' }}' === 'tasmeeh' ? 'true' : null"
            x-on:switch-tab.window="if($event.detail.tab === 'tasmeeh') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
            href="{{ route('teacher.tasmeeh') }}">
            {{ __('التسميع والمتابعة') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="users" wire:navigate :current="request()->routeIs('teacher.pairs')"
            href="{{ route('teacher.pairs') }}">
            {{ __('التسميع المتبادل') }}
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="{{ __('خطط المنظومات') }}" class="mt-4">
        <flux:sidebar.item icon="pencil-square" wire:navigate
            :current="request()->routeIs('teacher.ode-plan-creator')" href="{{ route('teacher.ode-plan-creator') }}">
            {{ __('إنشاء خطة منظومة') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="clipboard-document-list" wire:navigate
            :current="request()->routeIs('teacher.ode-plans')" href="{{ route('teacher.ode-plans') }}">
            {{ __('عرض الخطط المنشأة') }}
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="{{ __('التحفيز والمنافسة') }}" class="mt-4">
        <flux:sidebar.item icon="trophy"
            x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'leaderboards', url: '{{ route('teacher.leaderboards') }}' }); } else { Livewire.navigate('{{ route('teacher.leaderboards') }}'); }"
            x-bind:data-current="'{{ $initialTab ?? '' }}' === 'leaderboards' ? 'true' : null"
            x-on:switch-tab.window="if($event.detail.tab === 'leaderboards') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
            href="{{ route('teacher.leaderboards') }}">
            {{ __('مسابقات الحلقة') }}
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="{{ __('الاختبارات') }}" class="mt-4">
        <flux:sidebar.item icon="academic-cap" wire:navigate :current="request()->routeIs('teacher.student-exams*')"
            href="{{ route('teacher.student-exams') }}">
            {{ __('اختبارات الطلاب') }}
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="{{ __('التحضير') }}" class="mt-4">
        <flux:sidebar.item icon="calendar"
            x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'attendance', url: '{{ route('teacher.attendance') }}' }); } else { Livewire.navigate('{{ route('teacher.attendance') }}'); }"
            x-bind:data-current="'{{ $initialTab ?? '' }}' === 'attendance' ? 'true' : null"
            x-on:switch-tab.window="if($event.detail.tab === 'attendance') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
            href="{{ route('teacher.attendance') }}">
            سجل الحضور
        </flux:sidebar.item>
        <flux:sidebar.item icon="chart-bar" wire:navigate :current="request()->routeIs('teacher.discipline')"
            href="{{ route('teacher.discipline') }}">
            الانضباط الحضوري
        </flux:sidebar.item>
        <flux:sidebar.item icon="chart-pie" wire:navigate :current="request()->routeIs('teacher.quranic-discipline')"
            href="{{ route('teacher.quranic-discipline') }}">
            الانضباط القرآني
        </flux:sidebar.item>
        <flux:sidebar.item icon="exclamation-triangle" wire:navigate
            :current="request()->routeIs('teacher.exceeded-limits')" href="{{ route('teacher.exceeded-limits') }}">
            لائحة التجاوزات
        </flux:sidebar.item>
    </flux:sidebar.group>
</flux:sidebar.group>