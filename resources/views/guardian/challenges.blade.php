<x-layouts.role-shell role="guardian">
    <x-slot:title>
        {{ __('التحديات') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>
    <livewire:guardian.challenges-manager />
</x-layouts.role-shell>