<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationActivityWinner extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<GamificationActivityRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(GamificationActivityRound::class, 'round_id');
    }

    /** @return BelongsTo<GamificationActivityRank, $this> */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(GamificationActivityRank::class, 'rank_id');
    }

    /** @return BelongsTo<GamificationTeam, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(GamificationTeam::class, 'team_id');
    }
}
