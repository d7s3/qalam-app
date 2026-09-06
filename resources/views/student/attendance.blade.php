<x-layouts.role-shell>
    <x-slot:title>
        {{ __('سجل الانضباط') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:student.attendance />
    </div>
</x-layouts.role-shell>