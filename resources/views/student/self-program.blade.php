<x-layouts.role-shell>
    <x-slot:title>
        {{ __('البرنامج الذاتي') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:student.self-program />
    </div>
</x-layouts.role-shell>
