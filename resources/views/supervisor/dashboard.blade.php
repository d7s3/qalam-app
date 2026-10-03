<x-layouts.role-shell>
    <x-slot:title>
        {{ __('لوحة تحكم المشرف') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    {{-- His own programmes first — what he opened the page to see — and what
         they did after it, so a phone does not open on a screen of counts. --}}
    <livewire:supervisor.dashboard />

    <div class="space-y-8 p-6 md:p-8" dir="rtl">
        {{-- What the programmes did, counted inside this supervisor's reach. --}}
        <livewire:shared.activity-pulse />
    </div>
</x-layouts.role-shell>
