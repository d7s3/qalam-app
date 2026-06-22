<x-layouts.role-shell>
    <x-slot:title>
        {{ __('إنشاء خطة حديث لطالب') }}
    </x-slot:title>

    <x-slot:sidebar>
        @include('teacher.sidebar-nav')
    </x-slot:sidebar>

    <livewire:shared.hadith-plan-creator :student-id="request()->query('student_id')" />
</x-layouts.role-shell>
