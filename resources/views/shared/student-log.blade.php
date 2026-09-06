<x-layouts.role-shell>
    <x-slot:title>{{ __('السجل التربوي') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.student-log />
    </div>
</x-layouts.role-shell>
