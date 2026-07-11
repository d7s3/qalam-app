<x-layouts.role-shell>
    <x-slot:sidebar>
        @include('supervisor.sidebar-nav')
    </x-slot:sidebar>
    <livewire:supervisor.stage-report :stage-id="$stageId" />
</x-layouts.role-shell>
