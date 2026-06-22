<x-layouts.role-shell>
    <x-slot:sidebar>
        @include('supervisor.sidebar-nav')
    </x-slot:sidebar>
    <livewire:shared.hadith-plans-list role="supervisor" />
</x-layouts.role-shell>
