<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationNews extends Model
{
    use HasFactory;

    protected $table = 'gamification_news';

    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
        'data' => 'array',
    ];

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }
}
