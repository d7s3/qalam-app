<x-layouts.role-shell>

    <x-slot:title>
        {{ __('التقويم الأكاديمي') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:manager.academic-calendar />
</x-layouts.role-shell>