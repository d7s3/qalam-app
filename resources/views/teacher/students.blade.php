<x-layouts.role-shell>
    <x-slot:title>
        {{ __('إدارة الطلاب') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:teacher.student-manager />
</x-layouts.role-shell>
