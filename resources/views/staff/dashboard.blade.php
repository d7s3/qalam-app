<x-layouts.role-shell>
    <x-slot:title>{{ __('لوحة تحكم الموظف') }}</x-slot:title>

    <x-slot:sidebar>
        @include('staff.sidebar-nav')
    </x-slot:sidebar>

    <div class="md:p-8 space-y-4">
        <flux:heading size="xl">{{ __('أهلاً :name', ['name' => auth('staff')->user()->name]) }}</flux:heading>
        <flux:subheading>
            {{ __('دورك الحالي: :role', ['role' => auth('staff')->user()->staffRole?->label ?? __('غير محدد')]) }}
        </flux:subheading>
    </div>
</x-layouts.role-shell>
