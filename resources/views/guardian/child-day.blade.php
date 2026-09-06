<x-layouts.role-shell>
    <x-slot:title>{{ __('يوم ابني') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:guardian.child-day />
    </div>
</x-layouts.role-shell>
