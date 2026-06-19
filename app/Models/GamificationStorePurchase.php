<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamificationStorePurchase extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<GamificationStoreItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(GamificationStoreItem::class, 'store_item_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<GamificationTeam, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(GamificationTeam::class);
    }

    /** @return HasMany<GamificationPurchaseVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(GamificationPurchaseVote::class, 'store_purchase_id');
    }
}
