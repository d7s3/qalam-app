<x-layouts.role-shell>
    <x-slot:title>{{ __('بوابة الرسائل') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.portal />
    </div>
</x-layouts.role-shell>
