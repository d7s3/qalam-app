<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationTeamTaskAssignmentScore extends Model
{
    protected $table = 'gamification_team_task_assignment_scores';

    protected $guarded = [];

    /** @return BelongsTo<GamificationTeamTaskAssignment, $this> */
    public function assignment()
    {
        return $this->belongsTo(GamificationTeamTaskAssignment::class, 'assignment_id');
    }

    /** @return BelongsTo<GamificationTeamTaskCriterion, $this> */
    public function criterion()
    {
        return $this->belongsTo(GamificationTeamTaskCriterion::class, 'criterion_id');
    }
}
