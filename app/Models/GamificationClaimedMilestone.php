<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationClaimedMilestone extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<GamificationStreakMilestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(GamificationStreakMilestone::class, 'milestone_id');
    }
}
