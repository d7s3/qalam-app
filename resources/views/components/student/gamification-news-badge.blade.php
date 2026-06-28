<?php

use App\Models\GamificationNews;
use Livewire\Component;

new class extends Component
{
    public int $leaderboardId;

    /**
     * Latest news ids (newest first) for the competition. The unread count is
     * computed client-side by comparing these against the last-seen id kept in
     * the browser's localStorage, so opening the news tab clears the badge
     * per-device without any server-side read tracking.
     *
     * @var array<int, int>
     */
    public array $newsIds = [];

    public function mount(int $leaderboardId): void
    {
        $this->leaderboardId = $leaderboardId;
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->newsIds = GamificationNews::where('leaderboard_id', $this->leaderboardId)
            ->orderByDesc('id')
            ->limit(100)
            ->pluck('id')
            ->all();
    }
}; ?>

<div wire:poll.3s="refresh"
    x-data="{
        seen: Number(localStorage.getItem('gam-news-seen-{{ $leaderboardId }}') || 0),
        get unread() {
            return (this.$wire.newsIds || []).filter(id => id > this.seen).length;
        },
        markSeen() {
            let ids = this.$wire.newsIds || [];
            let max = ids.length ? Math.max(...ids) : 0;
            if (max > this.seen) { this.seen = max; }
            localStorage.setItem('gam-news-seen-{{ $leaderboardId }}', this.seen);
        }
    }"
    x-init="if (localStorage.getItem('student-gam-tab') === 'news') { markSeen(); }"
    x-on:news-opened.window="markSeen()"
    class="absolute -top-0.5 -end-0.5 pointer-events-none">
    <span x-show="unread > 0" x-cloak
        x-text="unread > 9 ? '9+' : unread"
        class="flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-rose-500 text-white text-[10px] font-black leading-none ring-2 ring-white shadow"></span>
</div>
