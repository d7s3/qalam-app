<x-layouts.role-shell>
    <x-slot:title>
        {{ __('رسائل خاصة للطلاب') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <livewire:manager.student-notices />
</x-layouts.role-shell>
