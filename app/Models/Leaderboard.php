<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Leaderboard extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_active_for_grading' => 'boolean',
        'settings' => 'json',
    ];

    public function circle()
    {
        return $this->belongsTo(Circle::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Supervisor::class);
    }

    /** Circles participating in this competition (supervisor-created multi-circle competitions) */
    public function circles()
    {
        return $this->belongsToMany(Circle::class, 'circle_leaderboard');
    }

    /**
     * Competitions any of these cohorts take part in: named on the competition
     * itself, or among the cohorts a supervisor opened it to.
     *
     * @param  iterable<int, int>  $circleIds
     */
    public function scopeTakenPartInBy(Builder $query, iterable $circleIds): void
    {
        $circleIds = collect($circleIds)->all();

        $query->where(fn (Builder $either) => $either
            ->whereIn('circle_id', $circleIds)
            ->orWhereHas('circles', fn (Builder $circles) => $circles->whereIn('circles.id', $circleIds)));
    }

    public function isSupervisorCompetition(): bool
    {
        return $this->supervisor_id !== null;
    }

    public function criteria()
    {
        return $this->hasMany(LeaderboardCriterion::class);
    }

    public function scores()
    {
        return $this->hasMany(LeaderboardScore::class);
    }

    /** @return HasMany<GamificationTeam, $this> */
    public function gamificationTeams(): HasMany
    {
        return $this->hasMany(GamificationTeam::class);
    }

    /** @return HasMany<GamificationStoreItem, $this> */
    public function gamificationStoreItems(): HasMany
    {
        return $this->hasMany(GamificationStoreItem::class);
    }

    /** @return HasMany<GamificationBadge, $this> */
    public function gamificationBadges(): HasMany
    {
        return $this->hasMany(GamificationBadge::class);
    }

    /** @return HasMany<GamificationStreakMilestone, $this> */
    public function gamificationStreakMilestones(): HasMany
    {
        return $this->hasMany(GamificationStreakMilestone::class);
    }

    /** @return HasMany<GamificationLevel, $this> */
    public function gamificationLevels(): HasMany
    {
        return $this->hasMany(GamificationLevel::class);
    }
}
