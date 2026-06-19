<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationPurchaseVote extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'vote' => 'boolean',
    ];

    /** @return BelongsTo<GamificationStorePurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(GamificationStorePurchase::class, 'store_purchase_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
