<x-layouts.role-shell>
    <x-slot:title>
        {{ __('الاختبارات') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:manager.student-exams />
</x-layouts.role-shell>