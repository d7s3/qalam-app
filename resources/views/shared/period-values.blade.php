<x-layouts.role-shell>
    <x-slot:title>{{ __('قيم الفترة') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.period-values />
    </div>
</x-layouts.role-shell>
