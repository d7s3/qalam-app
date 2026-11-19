<x-layouts.role-shell>
    <x-slot:title>{{ __('التزام الطلاب') }}</x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="p-1 md:p-8">
        <livewire:shared.commitment :role="$role" />
    </div>
</x-layouts.role-shell>
