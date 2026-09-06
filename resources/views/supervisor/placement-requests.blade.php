<x-layouts.role-shell>
    <x-slot:title>
        {{ __('طلبات التسكين') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:supervisor.placement-requests />
    </div>
</x-layouts.role-shell>
