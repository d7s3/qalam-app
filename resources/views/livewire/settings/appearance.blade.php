<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">إعدادات المظهر</flux:heading>

    <x-settings.layout heading="المظهر" subheading="تحديث إعدادات مظهر حسابك">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">فاتح</flux:radio>
            <flux:radio value="dark" icon="moon">داكن</flux:radio>
            <flux:radio value="system" icon="computer-desktop">تلقائي</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</section>
