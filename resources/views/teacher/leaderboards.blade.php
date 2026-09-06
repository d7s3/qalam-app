<x-layouts.role-shell>
    <x-slot:title>
        {{ __('لوحات المنافسة') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="p-1 md:p-8">
        <livewire:teacher.leaderboards />
    </div>
</x-layouts.role-shell>
