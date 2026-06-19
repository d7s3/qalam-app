<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationTeamTaskCriterion extends Model
{
    protected $table = 'gamification_team_task_criteria';

    protected $guarded = [];

    /** @return BelongsTo<GamificationTeamTask, $this> */
    public function task()
    {
        return $this->belongsTo(GamificationTeamTask::class, 'team_task_id');
    }

    /** @return HasMany<GamificationTeamTaskAssignmentScore, $this> */
    public function scores()
    {
        return $this->hasMany(GamificationTeamTaskAssignmentScore::class, 'criterion_id');
    }
}
