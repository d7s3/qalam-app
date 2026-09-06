<x-layouts.role-shell>
    <x-slot:title>
        {{ __('مساري القرآني') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:student.my-plan />
    </div>
</x-layouts.role-shell>