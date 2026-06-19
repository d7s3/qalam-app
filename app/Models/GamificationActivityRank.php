<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationActivityRank extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<GamificationActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(GamificationActivity::class, 'activity_id');
    }
}
