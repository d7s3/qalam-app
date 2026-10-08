<x-layouts.role-shell>
    <x-slot:title>
        {{ __('ردود النموذج') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>
    <livewire:supervisor.form-responses :formId="$formId" />
</x-layouts.role-shell>
