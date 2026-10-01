<?php

namespace App\Livewire\Teacher;

use App\Models\Circle;
use App\Models\Leaderboard;
use App\Services\LeaderboardService;
use App\Support\Scope;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeaderboardReport extends Component
{
    #[Locked]
    public $leaderboardId;

    public function mount($leaderboardId)
    {
        // Only a competition one of the reader's cohorts takes part in.
        $this->leaderboardId = Leaderboard::takenPartInBy(
            Scope::forRoute()->applyToCircles(Circle::query())->pluck('circles.id')
        )->findOrFail($leaderboardId)->id;
    }

    public function render()
    {
        $leaderboard = Leaderboard::with('criteria', 'circles')->findOrFail($this->leaderboardId);
        $service = new LeaderboardService;
        $standings = $service->getDetailedStandings($leaderboard);
        $standingsByTrack = $service->getStandingsByTrack($leaderboard);

        return view('livewire.teacher.leaderboard-report', [
            'leaderboard' => $leaderboard,
            'standings' => $standings,
            'standingsByTrack' => $standingsByTrack,
        ]);
    }
}
