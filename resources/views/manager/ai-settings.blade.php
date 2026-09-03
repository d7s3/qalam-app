<x-layouts.role-shell>
    <x-slot:title>
        {{ __('إعدادات الذكاء الاصطناعي') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="p-6 md:p-8 space-y-8" dir="rtl">
        <div>
            <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
                {{ __('إعدادات الذكاء الاصطناعي') }}
            </flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                {{ __('اختر المزوّد والموديل اللذين يعمل بهما المساعد الذكي، وأدِر مفاتيح المزوّدين') }}
            </flux:subheading>
        </div>

        <livewire:manager.ai-assistant-settings />
    </div>
</x-layouts.role-shell>
