<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A character the academy is working on for a stretch of days.
 *
 * See the migration for why it holds a practice and not only a name.
 */
class PeriodValue extends Model
{
    protected $fillable = [
        'stage_id',
        'circle_id',
        'starts_on',
        'ends_on',
        'title',
        'practice',
        'evidence',
        'created_by_id',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /**
     * The values running on a date for a student's programme and cohort.
     *
     * Compared as dates rather than as text: the cast writes `Y-m-d H:i:s`, and
     * a plain comparison drops the first and last day of the value's own run.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRunningOn(Builder $query, string $date, ?int $stageId, ?int $circleId): void
    {
        $query->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->where(function (Builder $q) use ($stageId, $circleId) {
                // Held against nothing means the whole academy is on it.
                $q->where(fn (Builder $all) => $all->whereNull('stage_id')->whereNull('circle_id'));

                if ($stageId !== null) {
                    $q->orWhere('stage_id', $stageId);
                }

                if ($circleId !== null) {
                    $q->orWhere('circle_id', $circleId);
                }
            });
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Circle, $this> */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }
}
