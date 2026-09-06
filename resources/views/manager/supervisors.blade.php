<x-layouts.role-shell>
    <x-slot:title>
        {{ __('المستخدمون') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:manager.user-directory initial-tab="supervisors" />
    </div>
</x-layouts.role-shell>
