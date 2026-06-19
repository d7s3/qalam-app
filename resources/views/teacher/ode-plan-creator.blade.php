<x-layouts.role-shell>
    <x-slot:title>
        {{ __('إنشاء خطة منظومة لطالب') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('teacher.sidebar-nav')
    </x-slot:sidebar>

    <livewire:shared.ode-plan-creator :student-id="request()->query('student_id')" />
</x-layouts.role-shell>
