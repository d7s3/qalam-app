<x-layouts.role-shell>
    <x-slot:title>
        {{ __('خطط المنظومات المنشأة') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('teacher.sidebar-nav')
    </x-slot:sidebar>

    <livewire:shared.ode-plans-list role="teacher" />
</x-layouts.role-shell>
