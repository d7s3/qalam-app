<x-layouts.role-shell>
    <x-slot:title>
        {{ __('تعديل نموذج') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('manager.sidebar-nav')
    </x-slot:sidebar>
    <livewire:supervisor.form-builder :form-id="$formId" />
</x-layouts.role-shell>
