<x-layouts.role-shell>
    <x-slot:title>
        {{ __('صلاحيات الصفحات') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('manager.sidebar-nav')
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:manager.role-permissions />
    </div>
</x-layouts.role-shell>
