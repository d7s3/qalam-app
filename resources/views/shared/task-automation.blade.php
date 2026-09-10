<x-layouts.role-shell>
    <x-slot:title>{{ __('المهام التلقائية') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.task-automation />
    </div>
</x-layouts.role-shell>
