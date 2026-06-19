<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationTeamTask extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard()
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /** @return HasMany<GamificationTeamTaskAssignment, $this> */
    public function assignments()
    {
        return $this->hasMany(GamificationTeamTaskAssignment::class, 'team_task_id');
    }

    /** @return HasMany<GamificationTeamTaskCriterion, $this> */
    public function criteria()
    {
        return $this->hasMany(GamificationTeamTaskCriterion::class, 'team_task_id');
    }
}
