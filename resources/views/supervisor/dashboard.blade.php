<x-layouts.role-shell>
    <x-slot:title>
        {{ __('لوحة تحكم المشرف') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('supervisor.sidebar-nav')
    </x-slot:sidebar>

    <livewire:supervisor.dashboard />
</x-layouts.role-shell>
