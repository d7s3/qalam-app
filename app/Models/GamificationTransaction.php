<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            // Only default xp_amount when it was not provided at all.
            // An explicit 0 means "coins only, no score" and must be respected.
            if ($transaction->type === 'earn' && is_null($transaction->xp_amount)) {
                $transaction->xp_amount = $transaction->amount;
            }
        });
    }

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<GamificationTeam, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(GamificationTeam::class, 'team_id');
    }
}
