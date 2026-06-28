<?php

namespace App\Models;

use App\Services\GamificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentOdeAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_ode_plan_id',
        'ode_path_day_id',
        'hifz_achievement',
        'review_achievement',
        'hifz_graded_at',
        'review_graded_at',
    ];

    protected static function booted(): void
    {
        // Drop any gamification points tied to this achievement when it is deleted,
        // so no orphaned transactions inflate student/team scores.
        static::deleting(function (StudentOdeAchievement $achievement) {
            GamificationService::clearTransactionsForReference(self::class, $achievement->id);
        });
    }

    protected $casts = [
        'hifz_graded_at' => 'datetime',
        'review_graded_at' => 'datetime',
    ];

    /** @return BelongsTo<StudentOdePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudentOdePlan::class, 'student_ode_plan_id');
    }

    /** @return BelongsTo<OdePathDay, $this> */
    public function pathDay(): BelongsTo
    {
        return $this->belongsTo(OdePathDay::class, 'ode_path_day_id');
    }

    /**
     * Delegates formatting to the underlying path day.
     */
    public function formatOdeRange(string $type = 'hifz'): ?string
    {
        return $this->pathDay?->formatOdeRange($type);
    }
}
