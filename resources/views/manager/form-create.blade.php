<x-layouts.role-shell>
    <x-slot:title>
        {{ __('إنشاء نموذج') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('manager.sidebar-nav')
    </x-slot:sidebar>
    <livewire:supervisor.form-builder />
</x-layouts.role-shell>
