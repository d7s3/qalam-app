<x-layouts.role-shell>
    <x-slot:title>
        {{ __('إدارة الموظفين') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('manager.sidebar-nav')
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:manager.staff-members />
    </div>
</x-layouts.role-shell>
