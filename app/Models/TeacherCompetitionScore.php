<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherCompetitionScore extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<TeacherCompetition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(TeacherCompetition::class, 'teacher_competition_id');
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    /** @return BelongsTo<TeacherCompetitionCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(TeacherCompetitionCriterion::class, 'criterion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function scoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
