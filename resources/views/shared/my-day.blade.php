<x-layouts.role-shell>
    <x-slot:title>{{ __('يومي') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.my-day />
    </div>
</x-layouts.role-shell>
