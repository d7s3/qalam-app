<x-layouts.role-shell>
    <x-slot:title>{{ __('رؤية الأحداث') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.event-visibility />
    </div>
</x-layouts.role-shell>
