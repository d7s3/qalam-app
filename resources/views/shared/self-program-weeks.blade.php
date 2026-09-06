<x-layouts.role-shell>
    <x-slot:title>{{ __('كتابة البرنامج الذاتي') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:supervisor.self-program-weeks />
    </div>
</x-layouts.role-shell>
