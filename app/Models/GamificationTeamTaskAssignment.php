<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationTeamTaskAssignment extends Model
{
    protected $table = 'gamification_team_task_assignments';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /** @return BelongsTo<GamificationTeamTask, $this> */
    public function task()
    {
        return $this->belongsTo(GamificationTeamTask::class, 'team_task_id');
    }

    /** @return BelongsTo<GamificationTeam, $this> */
    public function team()
    {
        return $this->belongsTo(GamificationTeam::class, 'team_id');
    }

    /** @return HasMany<GamificationTeamTaskAssignmentScore, $this> */
    public function scores()
    {
        return $this->hasMany(GamificationTeamTaskAssignmentScore::class, 'assignment_id');
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
