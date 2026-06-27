<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GamificationTrack extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /** @return BelongsToMany<Student, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'gamification_track_student', 'track_id', 'student_id');
    }
}
