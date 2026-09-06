<x-layouts.role-shell>
    <x-slot:title>
        {{ __('صلاحيات البرامج') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:manager.stage-access />
    </div>
</x-layouts.role-shell>
