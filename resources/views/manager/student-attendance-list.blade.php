<x-layouts.role-shell>
    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:manager.student-attendance-list :circle-id="request()->route('circleId')" :date="request()->route('date')" />
</x-layouts.role-shell>
