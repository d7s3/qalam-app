<x-layouts.role-shell>
    <x-slot:title>
        {{ __('لوحة تحكم المشرف') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="space-y-8 p-6 md:p-8" dir="rtl">
        {{-- What the programmes did, counted inside this supervisor's reach. --}}
        <livewire:shared.activity-pulse />
    </div>

    <livewire:supervisor.dashboard />
</x-layouts.role-shell>
