<x-layouts.role-shell>
    <x-slot:sidebar>
        @include('supervisor.sidebar-nav')
    </x-slot:sidebar>
    <livewire:supervisor.form-builder :formId="$formId" />
</x-layouts.role-shell>
