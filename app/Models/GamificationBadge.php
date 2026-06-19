<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GamificationBadge extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /** @return BelongsTo<LeaderboardCriterion, $this> */
    public function leaderboardCriterion(): BelongsTo
    {
        return $this->belongsTo(LeaderboardCriterion::class, 'leaderboard_criterion_id');
    }

    /** @return BelongsToMany<Student, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'gamification_badge_student', 'badge_id', 'student_id')
            ->withTimestamps();
    }
}
