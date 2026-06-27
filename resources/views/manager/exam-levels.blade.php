<x-layouts.role-shell>
    <x-slot:title>{{ __('مستويات الامتحانات') }}</x-slot:title>

    <x-slot:sidebar>
        @include('manager.sidebar-nav')
    </x-slot:sidebar>
    <livewire:manager.exam-levels />
</x-layouts.role-shell>
