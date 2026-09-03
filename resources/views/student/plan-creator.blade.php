<x-layouts.role-shell>
    <x-slot:title>
        {{ __('إعداد خطتي القرآنية') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:shared.plan-creator :edit="request('edit')" />
    </div>
</x-layouts.role-shell>