<x-layouts.role-shell>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                <flux:icon icon="squares-2x2" class="text-emerald-600 dark:text-emerald-400 size-6" />
            </div>
            <div>
                <flux:heading size="lg">البرنامج الذاتي والإثرائي</flux:heading>
                <flux:subheading>إعدادات حلقتك، ومحتواها الإثرائي، وتوزيعها اليومي، وتقدّم طلابها</flux:subheading>
            </div>
        </div>
    </x-slot>
    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:teacher.self-program-manager />
        </div>
    </div>
</x-layouts.role-shell>
