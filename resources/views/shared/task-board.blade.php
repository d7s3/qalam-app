<x-layouts.role-shell>
    <x-slot:title>{{ __('لوحة المهام') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.task-board />
    </div>
</x-layouts.role-shell>
