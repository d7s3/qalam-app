<?php

namespace App\Models;

use App\Services\GamificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentHadithAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_hadith_plan_id',
        'hadith_path_day_id',
        'hifz_achievement',
        'review_achievement',
        'hifz_graded_at',
        'review_graded_at',
    ];

    protected static function booted(): void
    {
        // Drop any gamification points tied to this achievement when it is deleted,
        // so no orphaned transactions inflate student/team scores.
        static::deleting(function (StudentHadithAchievement $achievement) {
            GamificationService::clearTransactionsForReference(self::class, $achievement->id);
        });
    }

    protected $casts = [
        'hifz_graded_at' => 'datetime',
        'review_graded_at' => 'datetime',
    ];

    /** @return BelongsTo<StudentHadithPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudentHadithPlan::class, 'student_hadith_plan_id');
    }

    /** @return BelongsTo<HadithPathDay, $this> */
    public function pathDay(): BelongsTo
    {
        return $this->belongsTo(HadithPathDay::class, 'hadith_path_day_id');
    }

    /**
     * Delegates formatting to the underlying path day.
     */
    public function formatHadithRange(string $type = 'hifz'): ?string
    {
        return $this->pathDay?->formatHadithRange($type);
    }
}
