<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationActivityRound extends Model
{
    protected $guarded = [];

    protected $casts = [
        'round_date' => 'date',
    ];

    /** @return BelongsTo<GamificationActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(GamificationActivity::class, 'activity_id');
    }

    /** @return HasMany<GamificationActivityWinner, $this> */
    public function winners(): HasMany
    {
        return $this->hasMany(GamificationActivityWinner::class, 'round_id');
    }
}
