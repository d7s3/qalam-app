<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherCompetitionCriterion extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<TeacherCompetition, $this> */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(TeacherCompetition::class, 'teacher_competition_id');
    }

    /** @return HasMany<TeacherCompetitionScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(TeacherCompetitionScore::class, 'criterion_id');
    }
}
