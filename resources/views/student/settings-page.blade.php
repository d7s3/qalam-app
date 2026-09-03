<x-layouts.role-shell :title="__('Settings')">
    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>
    <div class="p-6">
        <livewire:student.settings />
    </div>
</x-layouts.role-shell>