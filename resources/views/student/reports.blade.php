<x-layouts.role-shell>
    <x-slot:title>
        {{ __('التقارير') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:student.reports />
    </div>
</x-layouts.role-shell>
