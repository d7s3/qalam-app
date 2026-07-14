<x-layouts.role-shell>
    <x-slot:title>
        {{ __('المستخدمون') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('manager.sidebar-nav')
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:manager.user-directory initial-tab="students" />
    </div>
</x-layouts.role-shell>
