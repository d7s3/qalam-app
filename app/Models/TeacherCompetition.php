<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entirely independent of the student Leaderboard/gamification system — no
 * shared tables or foreign keys — so it can evolve without any risk to the
 * student competition feature.
 */
class TeacherCompetition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Supervisor, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_id');
    }

    /** @return BelongsToMany<Teacher, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'teacher_competition_participants', 'teacher_competition_id', 'teacher_id');
    }

    /** @return HasMany<TeacherCompetitionCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(TeacherCompetitionCriterion::class);
    }

    /** @return HasMany<TeacherCompetitionScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(TeacherCompetitionScore::class);
    }

    /**
     * Whether the competition is currently running: manually activated and
     * within its date range. Once end_date passes, this naturally flips to
     * false with no scheduled job needed.
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        return $today->gte($this->start_date) && $today->lte($this->end_date);
    }

    /**
     * Whether criteria may still be added/edited/removed — locked once any
     * score has been recorded, to avoid corrupting an in-progress evaluation.
     */
    public function criteriaAreLocked(): bool
    {
        return $this->scores()->whereNotNull('score')->exists();
    }
}
