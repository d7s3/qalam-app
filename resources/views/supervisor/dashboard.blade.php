<x-layouts.role-shell>
    <x-slot:title>
        {{ __('لوحة تحكم المشرف') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:supervisor.dashboard />
</x-layouts.role-shell>
