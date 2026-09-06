<x-layouts.role-shell>
    <x-slot:title>
        {{ __('إدارة البرامج') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:manager.stages />
</x-layouts.role-shell>
