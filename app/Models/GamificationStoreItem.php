<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationStoreItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_team_product' => 'boolean',
        'is_streak_freeze' => 'boolean',
        'target_date' => 'date',
        'require_assistant_approval' => 'boolean',
        'require_member_approval_count' => 'integer',
    ];

    /** @return BelongsTo<Leaderboard, $this> */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /** @return HasMany<GamificationStorePurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(GamificationStorePurchase::class, 'store_item_id');
    }
}
