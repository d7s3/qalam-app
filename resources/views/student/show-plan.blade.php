<x-layouts.role-shell>
    <x-slot:title>
        {{ __('تفاصيل الخطة') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:student.show-plan :planId="request()->route('id')" />
    </div>
</x-layouts.role-shell>