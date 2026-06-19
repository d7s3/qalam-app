<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaderboardCriterion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_enthusiasm_trigger' => 'boolean',
    ];

    public function leaderboard()
    {
        return $this->belongsTo(Leaderboard::class);
    }

    public function scores()
    {
        return $this->hasMany(LeaderboardScore::class);
    }
}
