{{--
    The teacher's sidebar: the register and the pages he opens every day
    first, the rest folded into groups that open on their own when he is on
    one of them.

    The register sat at the foot of some twenty-five entries, below pages a
    teacher opens once a term. Nothing was taken away; it is only ordered by
    how often it is wanted.

    The pages the teacher's app shell holds as tabs — register, students,
    recitation, plans, leaderboards — switch tab in place when the shell is on
    the page, and navigate when it is not.
--}}
@php
    use App\Services\MessagingService;
    use App\Support\RolePages;

    $shows = fn (string $page) => RolePages::isEnabled('teacher', $page);
    $on = fn (string ...$patterns) => request()->routeIs(...$patterns);
    $tab = $initialTab ?? '';

    $teacherUnreadMessages = MessagingService::unreadCountFor('teacher', auth('teacher')->id());
@endphp

<flux:sidebar.item icon="home" :href="route('teacher.dashboard')" :current="$on('teacher.dashboard')" wire:navigate>
    {{ __('الرئيسية') }}
</flux:sidebar.item>
@if($shows('teacher.attendance'))
    <flux:sidebar.item icon="calendar"
        x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'attendance', url: '{{ route('teacher.attendance') }}' }); } else { Livewire.navigate('{{ route('teacher.attendance') }}'); }"
        x-bind:data-current="'{{ $tab }}' === 'attendance' ? 'true' : null"
        x-on:switch-tab.window="if($event.detail.tab === 'attendance') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
        href="{{ route('teacher.attendance') }}">
        {{ __('سجل الحضور') }}
    </flux:sidebar.item>
@endif
@if($shows('teacher.students'))
    <flux:sidebar.item icon="users"
        x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'students', url: '{{ route('teacher.students') }}' }); } else { Livewire.navigate('{{ route('teacher.students') }}'); }"
        x-bind:data-current="'{{ $tab }}' === 'students' ? 'true' : null"
        x-on:switch-tab.window="if($event.detail.tab === 'students') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
        href="{{ route('teacher.students') }}">
        {{ __('إدارة الطلاب') }}
    </flux:sidebar.item>
@endif
@if($shows('teacher.tasmeeh'))
    <flux:sidebar.item icon="book-open"
        x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'tasmeeh', url: '{{ route('teacher.tasmeeh') }}' }); } else { Livewire.navigate('{{ route('teacher.tasmeeh') }}'); }"
        x-bind:data-current="'{{ $tab }}' === 'tasmeeh' ? 'true' : null"
        x-on:switch-tab.window="if($event.detail.tab === 'tasmeeh') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
        href="{{ route('teacher.tasmeeh') }}">
        {{ __('التسميع والمتابعة') }}
    </flux:sidebar.item>
@endif
@if($shows('teacher.messages'))
    <flux:sidebar.item icon="envelope" :href="route('teacher.messages')" :current="$on('teacher.messages')"
        :badge="$teacherUnreadMessages > 0 ? $teacherUnreadMessages : null" badge-color="rose" wire:navigate>
        {{ __('الرسائل') }}
    </flux:sidebar.item>
@endif

<flux:sidebar.group heading="المتابعة" class="grid">
    @if($shows('teacher.reports'))
        <flux:sidebar.item icon="chart-bar-square" :href="route('teacher.reports')" :current="$on('teacher.reports')" wire:navigate>
            {{ __('التقارير') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.student-exams'))
        <flux:sidebar.item icon="academic-cap" :href="route('teacher.student-exams')" :current="$on('teacher.student-exams*')" wire:navigate>
            {{ __('اختبارات الطلاب') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.leaderboards'))
        <flux:sidebar.item icon="trophy"
            x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'leaderboards', url: '{{ route('teacher.leaderboards') }}' }); } else { Livewire.navigate('{{ route('teacher.leaderboards') }}'); }"
            x-bind:data-current="'{{ $tab }}' === 'leaderboards' ? 'true' : null"
            x-on:switch-tab.window="if($event.detail.tab === 'leaderboards') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
            href="{{ route('teacher.leaderboards') }}">
            {{ __('مسابقات الدفعة') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="الخطط القرآنية" expandable :expanded="$on('teacher.student-plans', 'teacher.pairs', 'teacher.ode-plans') || $tab === 'plan-creator'" class="grid">
    @if($shows('teacher.plan-creator'))
        <flux:sidebar.item icon="pencil-square"
            x-on:click.prevent="if(document.getElementById('teacher-app-shell')) { $dispatch('switch-tab', { tab: 'plan-creator', url: '{{ route('teacher.plan-creator') }}' }); } else { Livewire.navigate('{{ route('teacher.plan-creator') }}'); }"
            x-bind:data-current="'{{ $tab }}' === 'plan-creator' ? 'true' : null"
            x-on:switch-tab.window="if($event.detail.tab === 'plan-creator') $el.setAttribute('data-current', 'true'); else $el.removeAttribute('data-current');"
            href="{{ route('teacher.plan-creator') }}">
            {{ __('إنشاء خطة طالب') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.student-plans'))
        <flux:sidebar.item icon="clipboard-document-list" :href="route('teacher.student-plans')" :current="$on('teacher.student-plans')" wire:navigate>
            {{ __('عرض الخطط المنشأة') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.pairs'))
        <flux:sidebar.item icon="users" :href="route('teacher.pairs')" :current="$on('teacher.pairs')" wire:navigate>
            {{ __('التسميع المتبادل') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.ode-plans'))
        <flux:sidebar.item icon="clipboard-document-list" :href="route('teacher.ode-plans')" :current="$on('teacher.ode-plans')" wire:navigate>
            {{ __('خطط المنظومات') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="البرنامج الذاتي" expandable :expanded="$on('teacher.self-program*')" class="grid">
    @if($shows('teacher.self-program'))
        <flux:sidebar.item icon="squares-2x2" :href="route('teacher.self-program')" :current="$on('teacher.self-program')" wire:navigate>
            {{ __('البرنامج الذاتي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.self-program-weeks'))
        <flux:sidebar.item icon="pencil-square" :href="route('teacher.self-program-weeks')" :current="$on('teacher.self-program-weeks')" wire:navigate>
            {{ __('كتابة البرنامج الذاتي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.self-program-record'))
        <flux:sidebar.item icon="clipboard-document-check" :href="route('teacher.self-program-record')" :current="$on('teacher.self-program-record')" wire:navigate>
            {{ __('سجلّ الطالب') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="الانضباط" expandable :expanded="$on('teacher.discipline', 'teacher.quranic-discipline', 'teacher.exceeded-limits')" class="grid">
    @if($shows('teacher.discipline'))
        <flux:sidebar.item icon="chart-bar" :href="route('teacher.discipline')" :current="$on('teacher.discipline')" wire:navigate>
            {{ __('الانضباط الحضوري') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.quranic-discipline'))
        <flux:sidebar.item icon="chart-pie" :href="route('teacher.quranic-discipline')" :current="$on('teacher.quranic-discipline')" wire:navigate>
            {{ __('الانضباط القرآني') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.exceeded-limits'))
        <flux:sidebar.item icon="exclamation-triangle" :href="route('teacher.exceeded-limits')" :current="$on('teacher.exceeded-limits')" wire:navigate>
            {{ __('لائحة التجاوزات') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="المهام واليوم" expandable :expanded="$on('teacher.my-day', 'teacher.task-board', 'teacher.task-automation')" class="grid">
    @if($shows('teacher.my-day'))
        <flux:sidebar.item icon="sun" :href="route('teacher.my-day')" :current="$on('teacher.my-day')" wire:navigate>
            {{ __('يومي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.task-board'))
        <flux:sidebar.item icon="rectangle-stack" :href="route('teacher.task-board')" :current="$on('teacher.task-board')" wire:navigate>
            {{ __('لوحة المهام') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.task-automation'))
        <flux:sidebar.item icon="arrow-path" :href="route('teacher.task-automation')" :current="$on('teacher.task-automation')" wire:navigate>
            {{ __('المهام التلقائية') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="التواصل والسجل" expandable :expanded="$on('teacher.portal', 'teacher.student-log', 'teacher.motivations', 'teacher.forms*')" class="grid">
    @if($shows('teacher.portal'))
        <flux:sidebar.item icon="megaphone" :href="route('teacher.portal')" :current="$on('teacher.portal')" wire:navigate>
            {{ __('بوابة الرسائل') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.student-log'))
        <flux:sidebar.item icon="book-open" :href="route('teacher.student-log')" :current="$on('teacher.student-log')" wire:navigate>
            {{ __('السجل التربوي') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.motivations'))
        <flux:sidebar.item icon="sparkles" :href="route('teacher.motivations')" :current="$on('teacher.motivations')" wire:navigate>
            {{ __('مستودع الشواهد') }}
        </flux:sidebar.item>
    @endif
    @if($shows('teacher.forms'))
        <flux:sidebar.item icon="document-text" :href="route('teacher.forms')" :current="$on('teacher.forms*')" wire:navigate>
            {{ __('الاستبانات والنماذج') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>
