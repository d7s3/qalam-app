<x-layouts.role-shell>
    <x-slot:title>
        {{ __('سجلّ البرنامج الذاتي') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.self-program-record role="supervisor" />
    </div>
</x-layouts.role-shell>
