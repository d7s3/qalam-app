<x-layouts.role-shell>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-sky-100 dark:bg-sky-900/30 rounded-lg">
                <flux:icon icon="chart-bar-square" class="text-sky-600 dark:text-sky-400 size-6" />
            </div>
            <div>
                <flux:heading size="lg">التقارير</flux:heading>
                <flux:subheading>اختر تقريراً ومدةً وتجميعاً، ثم اعرضه أو حمّله</flux:subheading>
            </div>
        </div>
    </x-slot>
    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:shared.report-runner :report="$report ?? null" />
        </div>
    </div>
</x-layouts.role-shell>
