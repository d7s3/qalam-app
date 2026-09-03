<x-layouts.role-shell>
    <x-slot:title>
        {{ __('الرسائل') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:messaging.inbox />
    </div>
</x-layouts.role-shell>
