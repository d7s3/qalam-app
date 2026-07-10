<x-layouts.role-shell>
    <x-slot:title>
        {{ __('التقويم') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('student.sidebar-nav')
    </x-slot:sidebar>

    <div class="md:p-8">
        <livewire:student.calendar />
    </div>
</x-layouts.role-shell>
