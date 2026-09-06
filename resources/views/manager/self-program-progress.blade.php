<x-layouts.role-shell>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                <flux:icon icon="chart-bar-square" class="text-emerald-600 dark:text-emerald-400 size-6" />
            </div>
            <div>
                <flux:heading size="lg">تقدّم البرنامج الذاتي</flux:heading>
                <flux:subheading>نظرة على المجمع كله</flux:subheading>
            </div>
        </div>
    </x-slot>
    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:shared.self-program-progress role="manager" />
        </div>
    </div>
</x-layouts.role-shell>
