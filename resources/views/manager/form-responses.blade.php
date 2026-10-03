<x-layouts.role-shell>
    <x-slot:title>
        {{ __('ردود النموذج') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('manager.sidebar-nav')
    </x-slot:sidebar>
    <livewire:supervisor.form-responses :form-id="$formId" />
</x-layouts.role-shell>
