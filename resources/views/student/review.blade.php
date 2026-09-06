<x-layouts.role-shell>
    <x-slot:title>
        {{ __('المراجعة') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:student.review />
    </div>
</x-layouts.role-shell>
