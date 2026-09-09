<x-layouts.role-shell>
    <x-slot:title>
        {{ __('المديرون') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:manager.managers />
    </div>
</x-layouts.role-shell>
