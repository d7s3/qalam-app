<x-layouts.role-shell>
    <x-slot:title>
        {{ __('لوحة تحكم المعلم') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="p-6 md:p-8 space-y-8" dir="rtl">
        <!-- Dashboard Main Volt Component -->
        <livewire:teacher.dashboard />

        <!-- Exceeded Limits (Violations) List -->
        <div>
            <flux:heading size="lg" class="mb-4">{{ __('لائحة التجاوزات والانذارات') }}</flux:heading>
            <livewire:shared.exceeded-limits />
        </div>
    </div>
</x-layouts.role-shell>
