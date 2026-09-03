<x-layouts.role-shell>
    <x-slot:title>
        {{ __('رصد النقاط اليدوية') }}
    </x-slot:title>

    <x-slot:sidebar>
        <x-role-sidebar />
    </x-slot:sidebar>

    <div class="p-1 md:p-8">
        <livewire:teacher.leaderboard-grade :leaderboardId="$leaderboardId" />
    </div>
</x-layouts.role-shell>
