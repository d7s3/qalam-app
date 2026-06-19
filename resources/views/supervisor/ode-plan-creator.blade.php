<x-layouts.role-shell>
    <x-slot:sidebar>
        @include('supervisor.sidebar-nav')
    </x-slot:sidebar>
    <livewire:shared.ode-plan-creator :student-id="request()->query('student_id')" />
</x-layouts.role-shell>
