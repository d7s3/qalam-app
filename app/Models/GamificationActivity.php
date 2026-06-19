<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationActivity extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /** @return HasMany<GamificationActivityRank, $this> */
    public function ranks(): HasMany
    {
        return $this->hasMany(GamificationActivityRank::class, 'activity_id');
    }

    /** @return HasMany<GamificationActivityRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(GamificationActivityRound::class, 'activity_id');
    }
}
