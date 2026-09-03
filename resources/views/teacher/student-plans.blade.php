<x-layouts.role-shell>
    <x-slot:title>
        {{ __('الخطط الدراسية المنشأة') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:teacher.student-plans-list />
</x-layouts.role-shell>
