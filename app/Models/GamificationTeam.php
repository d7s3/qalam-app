<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationTeam extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'shield_active_until' => 'datetime',
        'multiplier_active_until' => 'datetime',
    ];

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /** @return BelongsToMany<Student, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'gamification_team_student', 'team_id', 'student_id')
            ->withPivot('role');
    }

    /** @return HasMany<GamificationTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(GamificationTransaction::class, 'team_id');
    }
}
