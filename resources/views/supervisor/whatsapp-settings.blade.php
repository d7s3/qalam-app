<x-layouts.role-shell>
    <x-slot:title>
        إعدادات الواتساب
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="space-y-8">
        <livewire:supervisor.whatsapp-settings />

        <livewire:supervisor.absence-broadcast />
    </div>
</x-layouts.role-shell>
