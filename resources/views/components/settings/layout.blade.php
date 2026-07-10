<div class="flex items-start max-md:flex-col gap-6" dir="rtl">
    <div class="w-full pb-4 md:w-[220px] shrink-0">
        <flux:navlist aria-label="الإعدادات">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate icon="user-circle">الملف الشخصي</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate icon="shield-check">الأمان</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate icon="swatch">المظهر</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-2 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6">
        <flux:heading class="font-bold text-zinc-900 dark:text-white">{{ $heading ?? '' }}</flux:heading>
        <flux:subheading class="text-zinc-400">{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
