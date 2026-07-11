<x-layouts.role-shell :force-light="true">
    <x-slot:sidebar>
        @include('supervisor.sidebar-nav')
    </x-slot:sidebar>
    <livewire:supervisor.stage-report :stage-id="$stageId" />
</x-layouts.role-shell>
