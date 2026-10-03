<x-layouts.role-shell>
    <x-slot:title>
        {{ __('لوحة تحكم المعلم') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="p-6 md:p-8 space-y-8" dir="rtl">
        {{-- His day first — today's register, recitations and tasks, what a
             teacher between lessons opens the page for — and the counts after. --}}
        <livewire:teacher.dashboard />

        {{-- What his cohorts did, counted inside his own reach. --}}
        <livewire:shared.activity-pulse />

        <!-- Exceeded Limits (Violations) List -->
        <div>
            <flux:heading size="lg" class="mb-4">{{ __('لائحة التجاوزات والانذارات') }}</flux:heading>
            <livewire:shared.exceeded-limits />
        </div>
    </div>
</x-layouts.role-shell>
