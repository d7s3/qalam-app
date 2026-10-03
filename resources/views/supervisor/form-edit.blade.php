<x-layouts.role-shell>
    <x-slot:title>
        {{ __('تعديل نموذج') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>
    <livewire:supervisor.form-builder :formId="$formId" />
</x-layouts.role-shell>
