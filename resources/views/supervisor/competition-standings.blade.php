<x-layouts.role-shell>
    <x-slot:title>
        {{ __('الأوائل حسب المسار') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:supervisor.competition-standings :competition-id="$competitionId" />
</x-layouts.role-shell>
