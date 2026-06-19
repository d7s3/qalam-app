<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationStreakMilestone extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /** @return BelongsTo<GamificationBadge, $this> */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(GamificationBadge::class, 'reward_badge_id');
    }
}
