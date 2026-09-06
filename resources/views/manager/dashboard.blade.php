<x-layouts.role-shell>
    <x-slot:title>{{ __('لوحة تحكم المدير') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:manager.dashboard />
</x-layouts.role-shell>
