<x-layouts.role-shell>
    <x-slot:title>
        {{ __('مكتبتي') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:student.library />
    </div>
</x-layouts.role-shell>
