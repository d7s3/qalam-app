<x-layouts.role-shell>
    <x-slot:title>
        {{ __('سجل التسميع') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:teacher.student-recitation-log :studentId="$studentId" />
</x-layouts.role-shell>
